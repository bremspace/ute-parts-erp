' ==============================================================================
' ERP & POS System - Background Server Runner (No Console Window)
' Berjalan di background Windows secara independen tanpa jendela CMD
' ==============================================================================
Dim objShell, phpBin, port, docRoot, cmd
If WScript.Arguments.Count < 3 Then
    WScript.Quit 1
End If

phpBin = WScript.Arguments(0)
port = WScript.Arguments(1)
docRoot = WScript.Arguments(2)

Set objShell = CreateObject("WScript.Shell")
cmd = """" & phpBin & """ -S 0.0.0.0:" & port & " -t """ & docRoot & """"
' 0 = Hidden window, False = Async/non-blocking
objShell.Run cmd, 0, False
