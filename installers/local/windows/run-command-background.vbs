' ==============================================================================
' ERP & POS System - Background Command Runner (Hidden Window)
' Menjalankan perintah (seperti queue worker atau cloudflared) di background Windows
' tanpa memunculkan jendela konsol hitam CMD.
' ==============================================================================
Dim objShell, cmd
If WScript.Arguments.Count < 1 Then
    WScript.Quit 1
End If

cmd = WScript.Arguments(0)

Set objShell = CreateObject("WScript.Shell")
objShell.Run cmd, 0, False
