package main

import (
	"fmt"
	"net"
	"os"
	"os/exec"
	"os/signal"
	"path/filepath"
	"strings"
	"syscall"
	"time"
	"unsafe"
)

var (
	kernel32        = syscall.NewLazyDLL("kernel32.dll")
	user32          = syscall.NewLazyDLL("user32.dll")
	procCreateMutex = kernel32.NewProc("CreateMutexW")
	procCloseHandle = kernel32.NewProc("CloseHandle")

	procCreateJobObject         = kernel32.NewProc("CreateJobObjectW")
	procSetInformationJobObject = kernel32.NewProc("SetInformationJobObject")
	procAssignProcessToJobObject = kernel32.NewProc("AssignProcessToJobObject")

	procFindWindowW         = user32.NewProc("FindWindowW")
	procSetForegroundWindow = user32.NewProc("SetForegroundWindow")
)

const (
	ERROR_ALREADY_EXISTS = 183
	MUTEX_NAME           = "Global\\BremspaceERP_SingleInstance_Mutex"
	APP_TITLE            = "Bremspace ERP"
	DEFAULT_APP_PORT     = 1741
	DEFAULT_DB_PORT      = 13307
	DEFAULT_CGI_PORT     = 9000

	JobObjectExtendedLimitInformation = 9
	JOB_OBJECT_LIMIT_KILL_ON_JOB_CLOSE = 0x2000

	PROCESS_TERMINATE = 0x0001
	PROCESS_SET_QUOTA = 0x0100
)

type IO_COUNTERS struct {
	ReadOperationCount  uint64
	WriteOperationCount uint64
	OtherOperationCount uint64
	ReadTransferCount   uint64
	WriteTransferCount  uint64
	OtherTransferCount  uint64
}

type JOBOBJECT_BASIC_LIMIT_INFORMATION struct {
	PerProcessUserTimeLimit int64
	PerJobUserTimeLimit     int64
	LimitFlags              uint32
	MinimumWorkingSetSize   uintptr
	MaximumWorkingSetSize   uintptr
	ActiveProcessLimit      uint32
	Affinity                uintptr
	PriorityClass           uint32
	SchedulingClass         uint32
}

type JOBOBJECT_EXTENDED_LIMIT_INFORMATION struct {
	BasicLimitInformation JOBOBJECT_BASIC_LIMIT_INFORMATION
	IoInfo                IO_COUNTERS
	ProcessMemoryLimit    uintptr
	JobMemoryLimit        uintptr
	PeakProcessMemoryUsed uintptr
	PeakJobMemoryUsed     uintptr
}

func main() {
	// 1. Single Instance Check via Windows Named Mutex
	mutexNamePtr, _ := syscall.UTF16PtrFromString(MUTEX_NAME)
	hMutex, _, err := procCreateMutex.Call(0, 1, uintptr(unsafe.Pointer(mutexNamePtr)))
	if hMutex == 0 || err == syscall.Errno(ERROR_ALREADY_EXISTS) {
		bringExistingWindowToFront()
		os.Exit(0)
	}
	defer procCloseHandle.Call(hMutex)

	// Determine directories
	execPath, err := os.Executable()
	if err != nil {
		execPath = "."
	}
	baseDir := filepath.Dir(execPath)

	localAppData := os.Getenv("LOCALAPPDATA")
	if localAppData == "" {
		localAppData = filepath.Join(os.Getenv("USERPROFILE"), "AppData", "Local")
	}
	dataDir := filepath.Join(localAppData, "BremspaceERP", "db_data")
	storageDir := filepath.Join(localAppData, "BremspaceERP", "storage")
	configDir := filepath.Join(localAppData, "BremspaceERP", "config")

	_ = os.MkdirAll(dataDir, 0755)
	_ = os.MkdirAll(storageDir, 0755)
	_ = os.MkdirAll(configDir, 0755)

	// 2. Create Windows Job Object with KILL_ON_JOB_CLOSE
	hJob, err := createKillOnCloseJobObject()
	if err == nil && hJob != 0 {
		defer procCloseHandle.Call(hJob)
	}

	// 3. Negotiate Ports
	userAppPort, userDbPort := loadUserConfiguredPorts(baseDir, configDir)
	dbPort := findAvailablePort(userDbPort, userDbPort+50)
	appPort := findAvailablePort(userAppPort, userAppPort+50)
	cgiPort := findAvailablePort(DEFAULT_CGI_PORT, DEFAULT_CGI_PORT+50)

	fmt.Printf("[Bremspace Launcher] DB Port: %d, App Port: %d, FastCGI Port: %d\n", dbPort, appPort, cgiPort)

	// Prepare local config files
	engineDir := filepath.Join(baseDir, "db_engine")
	phpDir := filepath.Join(baseDir, "php")
	caddyDir := filepath.Join(baseDir, "caddy")
	appDir := filepath.Join(baseDir, "app")

	myIniPath := filepath.Join(configDir, "my.ini")
	phpIniPath := filepath.Join(configDir, "php.ini")
	caddyfilePath := filepath.Join(configDir, "Caddyfile")

	prepareMyIni(filepath.Join(baseDir, "config", "my.ini.template"), myIniPath, dbPort, engineDir, dataDir)
	preparePhpIni(filepath.Join(baseDir, "config", "php.desktop.ini"), phpIniPath, phpDir, storageDir)
	prepareEnv(filepath.Join(baseDir, "config", ".env.desktop.template"), filepath.Join(appDir, ".env"), dbPort, appPort, configDir, dataDir)
	prepareCaddyfile(filepath.Join(baseDir, "config", "Caddyfile.desktop"), caddyfilePath, appPort, cgiPort, appDir)

	// 4. Initialize Database if needed
	if isDirEmpty(dataDir) {
		fmt.Println("[Bremspace Launcher] Initializing database data directory...")
		installDbExe := filepath.Join(engineDir, "bin", "mysql_install_db.exe")
		cmdInstall := exec.Command(installDbExe, fmt.Sprintf("--datadir=%s", dataDir), "--default-storage-engine=InnoDB")
		cmdInstall.SysProcAttr = &syscall.SysProcAttr{HideWindow: true}
		_ = cmdInstall.Run()
	}

	// 5. Start MariaDB Portable
	fmt.Println("[Bremspace Launcher] Starting MariaDB Portable...")
	mysqldExe := filepath.Join(engineDir, "bin", "mysqld.exe")
	cmdMysqld := exec.Command(mysqldExe, fmt.Sprintf("--defaults-file=%s", myIniPath))
	cmdMysqld.SysProcAttr = &syscall.SysProcAttr{HideWindow: true}
	if err := cmdMysqld.Start(); err != nil {
		fmt.Printf("[Error] Failed to start MariaDB: %v\n", err)
	} else if hJob != 0 {
		assignProcessToJob(hJob, cmdMysqld.Process)
	}

	waitForPort(dbPort, 15*time.Second)

	// 6. Run Database Migrations
	phpExe := filepath.Join(phpDir, "php.exe")
	phpCgiExe := filepath.Join(phpDir, "php-cgi.exe")
	artisanPath := filepath.Join(appDir, "artisan")
	cmdMigrate := exec.Command(phpExe, "-c", phpIniPath, artisanPath, "migrate", "--force")
	cmdMigrate.SysProcAttr = &syscall.SysProcAttr{HideWindow: true}
	_ = cmdMigrate.Run()

	// 7. Start PHP-CGI FastCGI Daemon
	fmt.Println("[Bremspace Launcher] Starting PHP-CGI daemon...")
	cmdPhpCgi := exec.Command(phpCgiExe, "-b", fmt.Sprintf("127.0.0.1:%d", cgiPort), "-c", phpIniPath)
	cmdPhpCgi.Env = append(os.Environ(), "PHP_FCGI_CHILDREN=4", "PHP_FCGI_MAX_REQUESTS=1000")
	cmdPhpCgi.SysProcAttr = &syscall.SysProcAttr{HideWindow: true}
	if err := cmdPhpCgi.Start(); err != nil {
		fmt.Printf("[Error] Failed to start PHP-CGI: %v\n", err)
	} else if hJob != 0 {
		assignProcessToJob(hJob, cmdPhpCgi.Process)
	}

	waitForPort(cgiPort, 10*time.Second)

	// 8. Start Caddy Web Server
	fmt.Println("[Bremspace Launcher] Starting Caddy Server...")
	caddyExe := filepath.Join(caddyDir, "caddy.exe")
	cmdCaddy := exec.Command(caddyExe, "run", "--config", caddyfilePath, "--adapter", "caddyfile")
	cmdCaddy.SysProcAttr = &syscall.SysProcAttr{HideWindow: true}
	if err := cmdCaddy.Start(); err != nil {
		fmt.Printf("[Error] Failed to start Caddy: %v\n", err)
	} else if hJob != 0 {
		assignProcessToJob(hJob, cmdCaddy.Process)
	}

	waitForPort(appPort, 10*time.Second)

	// 9. Launch WebView2 App Mode
	appUrl := fmt.Sprintf("http://127.0.0.1:%d", appPort)
	fmt.Printf("[Bremspace Launcher] Opening App: %s\n", appUrl)
	msedgePath := findEdgeExecutable()
	var cmdUi *exec.Cmd
	if msedgePath != "" {
		cmdUi = exec.Command(msedgePath, fmt.Sprintf("--app=%s", appUrl), "--window-size=1280,800")
	} else {
		cmdUi = exec.Command("cmd", "/c", "start", appUrl)
	}
	if err := cmdUi.Start(); err == nil && hJob != 0 {
		assignProcessToJob(hJob, cmdUi.Process)
	}

	sigChan := make(chan os.Signal, 1)
	signal.Notify(sigChan, os.Interrupt, syscall.SIGTERM)

	doneChan := make(chan struct{})
	go func() {
		if cmdUi != nil && cmdUi.Process != nil {
			_ = cmdUi.Wait()
		}
		close(doneChan)
	}()

	select {
	case <-sigChan:
		fmt.Println("[Bremspace Launcher] Shutdown signal received.")
	case <-doneChan:
		fmt.Println("[Bremspace Launcher] User closed window.")
	}

	// 10. Graceful MariaDB Shutdown
	fmt.Println("[Bremspace Launcher] Shutting down MariaDB...")
	mysqladminExe := filepath.Join(engineDir, "bin", "mysqladmin.exe")
	cmdShutdown := exec.Command(mysqladminExe, "-u", "root", fmt.Sprintf("-P%d", dbPort), "shutdown")
	cmdShutdown.SysProcAttr = &syscall.SysProcAttr{HideWindow: true}
	_ = cmdShutdown.Run()

	time.Sleep(1 * time.Second)
	fmt.Println("[Bremspace Launcher] Goodbye.")
}

func createKillOnCloseJobObject() (uintptr, error) {
	hJob, _, err := procCreateJobObject.Call(0, 0)
	if hJob == 0 {
		return 0, err
	}

	var info JOBOBJECT_EXTENDED_LIMIT_INFORMATION
	info.BasicLimitInformation.LimitFlags = JOB_OBJECT_LIMIT_KILL_ON_JOB_CLOSE

	ret, _, err := procSetInformationJobObject.Call(
		hJob,
		uintptr(JobObjectExtendedLimitInformation),
		uintptr(unsafe.Pointer(&info)),
		uintptr(unsafe.Sizeof(info)),
	)
	if ret == 0 {
		return hJob, err
	}
	return hJob, nil
}

func assignProcessToJob(hJob uintptr, proc *os.Process) {
	if proc == nil || hJob == 0 {
		return
	}
	hProcess, err := syscall.OpenProcess(PROCESS_SET_QUOTA|PROCESS_TERMINATE, false, uint32(proc.Pid))
	if err == nil && hProcess != 0 {
		_, _, _ = procAssignProcessToJobObject.Call(hJob, uintptr(hProcess))
		_ = syscall.CloseHandle(hProcess)
	}
}

func bringExistingWindowToFront() {
	titlePtr, _ := syscall.UTF16PtrFromString(APP_TITLE)
	hWnd, _, _ := procFindWindowW.Call(0, uintptr(unsafe.Pointer(titlePtr)))
	if hWnd != 0 {
		_, _, _ = procSetForegroundWindow.Call(hWnd)
	}
}

func findAvailablePort(startPort, endPort int) int {
	for port := startPort; port <= endPort; port++ {
		addr := fmt.Sprintf("127.0.0.1:%d", port)
		listener, err := net.Listen("tcp", addr)
		if err == nil {
			_ = listener.Close()
			return port
		}
	}
	return startPort
}

func loadUserConfiguredPorts(baseDir, configDir string) (int, int) {
	appPort := DEFAULT_APP_PORT
	dbPort := DEFAULT_DB_PORT

	candidates := []string{
		filepath.Join(configDir, "ports.ini"),
		filepath.Join(baseDir, "config", "ports.ini"),
	}

	for _, p := range candidates {
		data, err := os.ReadFile(p)
		if err == nil {
			lines := strings.Split(string(data), "\n")
			for _, line := range lines {
				line = strings.TrimSpace(line)
				if strings.HasPrefix(line, "#") || !strings.Contains(line, "=") {
					continue
				}
				parts := strings.SplitN(line, "=", 2)
				key := strings.ToLower(strings.TrimSpace(parts[0]))
				val := strings.TrimSpace(parts[1])

				var intVal int
				if n, _ := fmt.Sscanf(val, "%d", &intVal); n == 1 && intVal > 1024 && intVal < 65535 {
					if key == "app_port" {
						appPort = intVal
					} else if key == "db_port" {
						dbPort = intVal
					}
				}
			}
			break
		}
	}

	return appPort, dbPort
}

func waitForPort(port int, timeout time.Duration) bool {
	deadline := time.Now().Add(timeout)
	addr := fmt.Sprintf("127.0.0.1:%d", port)
	for time.Now().Before(deadline) {
		conn, err := net.DialTimeout("tcp", addr, 200*time.Millisecond)
		if err == nil {
			_ = conn.Close()
			return true
		}
		time.Sleep(300 * time.Millisecond)
	}
	return false
}

func isDirEmpty(name string) bool {
	f, err := os.Open(name)
	if err != nil {
		return true
	}
	defer f.Close()
	_, err = f.Readdirnames(1)
	return err != nil
}

func findEdgeExecutable() string {
	paths := []string{
		filepath.Join(os.Getenv("ProgramFiles(x86)"), "Microsoft", "Edge", "Application", "msedge.exe"),
		filepath.Join(os.Getenv("ProgramFiles"), "Microsoft", "Edge", "Application", "msedge.exe"),
		filepath.Join(os.Getenv("LOCALAPPDATA"), "Microsoft", "Edge", "Application", "msedge.exe"),
	}
	for _, p := range paths {
		if _, err := os.Stat(p); err == nil {
			return p
		}
	}
	return ""
}

func prepareMyIni(templatePath, destPath string, port int, engineDir, dataDir string) {
	content, err := os.ReadFile(templatePath)
	if err != nil {
		return
	}
	s := string(content)
	s = strings.ReplaceAll(s, "{{DB_PORT}}", fmt.Sprintf("%d", port))
	s = strings.ReplaceAll(s, "{{ENGINE_DIR}}", filepath.ToSlash(engineDir))
	s = strings.ReplaceAll(s, "{{DATA_DIR}}", filepath.ToSlash(dataDir))
	s = strings.ReplaceAll(s, "{{TMP_DIR}}", filepath.ToSlash(filepath.Join(dataDir, "tmp")))
	_ = os.WriteFile(destPath, []byte(s), 0644)
}

func preparePhpIni(templatePath, destPath string, phpDir, storageDir string) {
	content, err := os.ReadFile(templatePath)
	if err != nil {
		return
	}
	s := string(content)
	s = strings.ReplaceAll(s, "{{PHP_DIR}}", filepath.ToSlash(phpDir))
	s = strings.ReplaceAll(s, "{{STORAGE_DIR}}", filepath.ToSlash(storageDir))
	_ = os.WriteFile(destPath, []byte(s), 0644)
}

func prepareEnv(templatePath, destPath string, dbPort, appPort int, configDir, dataDir string) {
	content, err := os.ReadFile(templatePath)
	if err != nil {
		return
	}
	s := string(content)
	s = strings.ReplaceAll(s, "{{DB_PORT}}", fmt.Sprintf("%d", dbPort))
	s = strings.ReplaceAll(s, "{{APP_PORT}}", fmt.Sprintf("%d", appPort))
	s = strings.ReplaceAll(s, "{{LICENSE_PATH}}", filepath.ToSlash(filepath.Join(configDir, "license.key")))
	s = strings.ReplaceAll(s, "{{DATA_DIR}}", filepath.ToSlash(dataDir))
	s = strings.ReplaceAll(s, "{{APP_KEY}}", "base64:3f4E+BremspaceDefaultKeyForDesktopLocalAppMode=")
	_ = os.WriteFile(destPath, []byte(s), 0644)
}

func prepareCaddyfile(templatePath, destPath string, appPort, cgiPort int, appDir string) {
	content, err := os.ReadFile(templatePath)
	if err != nil {
		return
	}
	s := string(content)
	s = strings.ReplaceAll(s, "{{APP_PORT}}", fmt.Sprintf("%d", appPort))
	s = strings.ReplaceAll(s, "{{CGI_PORT}}", fmt.Sprintf("%d", cgiPort))
	s = strings.ReplaceAll(s, "{{APP_DIR}}", filepath.ToSlash(appDir))
	_ = os.WriteFile(destPath, []byte(s), 0644)
}
