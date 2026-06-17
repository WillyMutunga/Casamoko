const fs = require('fs');
let code = fs.readFileSync('frontend/src/App.tsx', 'utf8');

// Remove handleSimulateMO function
code = code.replace(/  const handleSimulateMO = async \(e: React\.FormEvent\) => \{[\s\S]*?  \};\n/g, '');

// Remove Inbound MO Simulator Widget (which is inside a glass-panel)
// The widget is surrounded by {/* Inbound MO Sandbox Simulator Widget */} ... {/* End Sandbox Widget */}
// Oh wait, I didn't add "End Sandbox Widget" comment.
// I'll just remove the specific form
code = code.replace(/<p className="text-\[10px\] text-gray-400 mb-4">Simulate inbound subscriber requests[\s\S]*?<\/form>/g, '');
code = code.replace(/<span className="px-2 py-0.5 bg-amber-500\/10 text-amber-400 border border-amber-500\/20 text-\[9px\] font-bold rounded tracking-wider uppercase">Sandbox<\/span>/g, '');

// Remove handleTestConnection sandbox fallback
code = code.replace(/console\.warn\("Connection test API failed, utilizing sandbox simulation\."\);[\s\S]*?          setTestingRouteId\(null\);\n        \}, 600\);/g, 'setShortcodeStatus("Connection test API failed");\n      setTestingRouteId(null);');

// Remove wallet adjust sandbox fallback
code = code.replace(/console\.warn\("API wallet adjust failed, using local sandbox fallback\.", err\);[\s\S]*?alert\(`Wallet balance adjusted by \$\$\{amount\.toFixed\(2\)\} \(Sandbox Mode\)\.`\);/g, 'alert("API wallet adjust failed.");');

// Remove save route config sandbox fallback
code = code.replace(/console\.warn\("Save route config failed, utilizing sandbox local sync\."\);[\s\S]*?alert\("Route configuration saved successfully \(Local Sandbox Mode\)\!"\);/g, 'alert("Save route config failed.");');

// Remove backend team addition sandbox fallback
code = code.replace(/console\.warn\("Backend team addition failed, using sandbox fallback", err\);[\s\S]*?setTeamStatus\(`Sub-user "\$\{newMemberName\}" added successfully as \$\{newMemberSubRole\} \(Local Sandbox Mode\)\.`\);/g, 'setTeamStatus("Backend team addition failed.");');

// Replace "No active conversation sessions. Use the sandbox simulator to trigger one."
code = code.replace(/Use the sandbox simulator to trigger one\./g, 'Wait for an inbound message to start a thread.');

fs.writeFileSync('frontend/src/App.tsx', code);
console.log('Cleaned up App.tsx fallbacks');
