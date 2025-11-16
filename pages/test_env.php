<?php
echo "SLICIE_IP_ADDRESS: " . getenv('SLICIE_IP_ADDRESS') . "\n";
echo "SLICIE_USER: " . getenv('SLICIE_USER') . "\n";
echo "SLICIE_PASSWORD exists: " . (getenv('SLICIE_PASSWORD') ? 'yes' : 'no') . "\n";
