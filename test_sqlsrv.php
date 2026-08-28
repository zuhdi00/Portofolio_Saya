<?php
if (extension_loaded('sqlsrv')) {
    echo "SQLSRV extension is loaded!";
} else {
    echo "SQLSRV NOT loaded! Check error log.";
}