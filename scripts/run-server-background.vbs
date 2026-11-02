' ==============================================================================
' ERP & POS System - Background Server Runner (No Console Window)
' Berjalan di background Windows secara independen tanpa jendela CMD
' ==============================================================================
Dim objShell, objFSO, phpBin, port, docRoot, logFile, cmd
If WScript.Arguments.Count < 3 Then
    WScript.Quit 1
End If

phpBin = WScript.Arguments(0)
port = WScript.Arguments(1)
docRoot = WScript.Arguments(2)

Set objFSO = CreateObject("Scripting.FileSystemObject")
Dim baseDir
baseDir = objFSO.GetParentFolderName(docRoot)

If WScript.Arguments.Count >= 4 Then
    logFile = WScript.Arguments(3)
Else
    logFile = baseDir & "\storage\logs\server.log"
End If

' Pastikan direktori log tersedia
Dim logsDir
logsDir = baseDir & "\storage\logs"
If Not objFSO.FolderExists(logsDir) Then
    On Error Resume Next
    If Not objFSO.FolderExists(baseDir & "\storage") Then
        objFSO.CreateFolder(baseDir & "\storage")
    End If
    objFSO.CreateFolder(logsDir)
    On Error GoTo 0
End If

Set objShell = CreateObject("WScript.Shell")
cmd = "%comspec% /c """"" & phpBin & """ -S 0.0.0.0:" & port & " -t """ & docRoot & """ >> """ & logFile & """ 2>&1"""
' 0 = Hidden window, False = Async/non-blocking
objShell.Run cmd, 0, False
