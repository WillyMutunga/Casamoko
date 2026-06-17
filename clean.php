<?php
$content = file_get_contents('frontend/src/App.tsx');
$content = preg_replace('/\{\/\* Inbound MO Sandbox Simulator Widget \*\/\}.*?<\/div>/s', '', $content);
$content = preg_replace('/const handleSimulateMO = async \(e: React\.FormEvent\) => \{.*?\};\n/s', '', $content);
file_put_contents('frontend/src/App.tsx', $content);
echo "Cleaned up App.tsx Sandbox code\n";
