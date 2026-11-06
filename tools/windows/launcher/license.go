package main

import (
	"crypto/hmac"
	"crypto/sha256"
	"encoding/hex"
	"encoding/json"
	"fmt"
	"os"
	"os/exec"
	"path/filepath"
	"regexp"
	"strings"
	"syscall"
	"unsafe"
)

const (
	HWID_SALT    = "BREMSPACE_HWID_V1_2026"
	LICENSE_SALT = "BREMSPACE_LICENSE_SECURITY_ENGINE_V1"

	MB_OK          = 0x00000000
	MB_ICONERROR   = 0x00000010
	MB_ICONWARNING = 0x00000030
)

type LicenseFilePayload struct {
	Status      string `json:"status"`
	Challenge   string `json:"challenge"`
	Key         string `json:"key"`
	VerifiedAt  string `json:"verified_at"`
	MachineHash string `json:"machine_hash"`
}

var procMessageBoxW = user32.NewProc("MessageBoxW")

func ShowNativeMessageBox(title, message string, uType uint) {
	titlePtr, _ := syscall.UTF16PtrFromString(title)
	msgPtr, _ := syscall.UTF16PtrFromString(message)
	procMessageBoxW.Call(0, uintptr(unsafe.Pointer(msgPtr)), uintptr(unsafe.Pointer(titlePtr)), uintptr(uType))
}

func getRawHardwareUUID() string {
	cmd := exec.Command("powershell", "-NoProfile", "-Command", "(Get-CimInstance -ClassName Win32_ComputerSystemProduct).UUID")
	cmd.SysProcAttr = &syscall.SysProcAttr{HideWindow: true}
	out, err := cmd.Output()
	if err == nil {
		raw := strings.TrimSpace(string(out))
		reg := regexp.MustCompile(`[^A-Za-z0-9\-]`)
		cleaned := reg.ReplaceAllString(raw, "")
		if cleaned != "" && !strings.Contains(strings.ToLower(cleaned), "uuid") {
			return cleaned
		}
	}

	cmdWmic := exec.Command("wmic", "csproduct", "get", "uuid")
	cmdWmic.SysProcAttr = &syscall.SysProcAttr{HideWindow: true}
	outWmic, errWmic := cmdWmic.Output()
	if errWmic == nil {
		lines := strings.Split(string(outWmic), "\n")
		for _, line := range lines {
			trimmed := strings.TrimSpace(line)
			if trimmed != "" && !strings.EqualFold(trimmed, "uuid") {
				reg := regexp.MustCompile(`[^A-Za-z0-9\-]`)
				cleaned := reg.ReplaceAllString(trimmed, "")
				if cleaned != "" {
					return cleaned
				}
			}
		}
	}

	hostname, _ := os.Hostname()
	if hostname != "" {
		return hostname
	}
	return "WIN-GENERIC"
}

func computeMachineChallenge(rawUUID string) string {
	sum := sha256.Sum256([]byte(HWID_SALT + ":" + rawUUID))
	hashStr := strings.ToUpper(hex.EncodeToString(sum[:]))
	return fmt.Sprintf("BREM-%s-%s-%s", hashStr[0:4], hashStr[4:8], hashStr[8:12])
}

func computeActivationSignature(challenge string) string {
	cleanChallenge := strings.ToUpper(strings.TrimSpace(challenge))
	mac := hmac.New(sha256.New, []byte(LICENSE_SALT))
	mac.Write([]byte(cleanChallenge))
	hashStr := strings.ToUpper(hex.EncodeToString(mac.Sum(nil)))
	return fmt.Sprintf("ACT-%s-%s-%s-%s", hashStr[0:4], hashStr[4:8], hashStr[8:12], hashStr[12:16])
}

func verifyLicense(licensePath, storageDir string) (bool, string, string) {
	rawUUID := getRawHardwareUUID()
	currentChallenge := computeMachineChallenge(rawUUID)

	targetPath := licensePath
	if _, err := os.Stat(targetPath); os.IsNotExist(err) {
		fallbackStorage := filepath.Join(storageDir, "license.key")
		if _, errF := os.Stat(fallbackStorage); errF == nil {
			targetPath = fallbackStorage
		} else {
			return false, currentChallenge, "Berkas lisensi (license.key) belum ditemukan di perangkat ini."
		}
	}

	data, err := os.ReadFile(targetPath)
	if err != nil {
		return false, currentChallenge, "Gagal membaca berkas lisensi."
	}

	var payload LicenseFilePayload
	if err := json.Unmarshal(data, &payload); err != nil {
		return false, currentChallenge, "Format berkas lisensi rusak atau tidak valid."
	}

	// 1. Anti-Duplikasi Mesin: Cek kecocokan challenge dengan hardware saat ini
	if !strings.EqualFold(payload.Challenge, currentChallenge) {
		return false, currentChallenge, fmt.Sprintf("Identitas hardware mesin (%s) tidak cocok dengan lisensi terdaftar (%s). Sistem menolak cloning disk / duplikasi.", currentChallenge, payload.Challenge)
	}

	// 2. Verifikasi kriptografis Activation Key
	expectedKey := computeActivationSignature(currentChallenge)
	if !strings.EqualFold(strings.TrimSpace(payload.Key), expectedKey) {
		return false, currentChallenge, "Kode aktivasi lisensi tidak sah (Invalid Signature)."
	}

	return true, currentChallenge, ""
}

func verifyCoreFilesIntegrity(appDir string) (bool, string) {
	coreFiles := []string{
		filepath.Join(appDir, "app", "Http", "Middleware", "EnsureLicenseValid.php"),
		filepath.Join(appDir, "scripts", "license", "LicenseVerifier.php"),
		filepath.Join(appDir, "scripts", "license", "HardwareIdentifier.php"),
	}

	for _, file := range coreFiles {
		info, err := os.Stat(file)
		if os.IsNotExist(err) {
			return false, fmt.Sprintf("Berkas keamanan sistem hilang: %s", filepath.Base(file))
		}
		if info.Size() == 0 {
			return false, fmt.Sprintf("Berkas keamanan sistem kosong / tampered: %s", filepath.Base(file))
		}
	}

	return true, ""
}
