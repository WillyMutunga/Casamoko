const fs = require('fs');
let code = fs.readFileSync('frontend/src/App.tsx', 'utf8');
const uiFragment = fs.readFileSync('phase1_ui.txt', 'utf8');

// 1. Add missing lucide-react icons
if (!code.includes('Webhook,')) {
  code = code.replace(/import \{ Key, Copy, Trash2, Code, Library \} from 'lucide-react';/,
    "import { Key, Copy, Trash2, Code, Library, Webhook, TerminalSquare, FileCode2, MessageSquareDashed } from 'lucide-react';");
}

// 2. Insert new states after currentPage
if (!code.includes('const [devApiKey, setDevApiKey]')) {
  code = code.replace(
    /const \[currentPage, setCurrentPage\] = useState<string>\('dashboard'\);/,
    `const [currentPage, setCurrentPage] = useState<string>('dashboard');
  const [devApiKey, setDevApiKey] = useState('live_csmk_' + Math.random().toString(36).substring(2, 26));
  const [devWebhookUrl, setDevWebhookUrl] = useState('https://');
  const [templatesList, setTemplatesList] = useState([
    { id: 1, name: 'Invoice Reminder', content: 'Dear {first_name}, your invoice of {amount} is due.', status: 'APPROVED' },
    { id: 2, name: 'OTP Auth', content: 'Your Casamoko code is {otp}', status: 'PENDING' }
  ]);
  const [newTemplateName, setNewTemplateName] = useState('');
  const [newTemplateContent, setNewTemplateContent] = useState('');`
  );
}

// 3. Insert sidebar buttons for CLIENT
if (!code.includes('setCurrentPage(\\'developer\\')')) {
  code = code.replace(
    /<button \n\s*onClick=\{\(\) => setCurrentPage\('sender_ids'\)\}[\s\S]*?<\/button>/,
    `$&
                  <button 
                    onClick={() => setCurrentPage('templates')} 
                    className={\`w-full flex items-center gap-4 px-4 py-3 rounded-xl font-medium transition-all \${currentPage === 'templates' ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-500/20' : 'text-gray-400 hover:bg-slate-900/60 hover:text-white'}\`}
                  >
                    <MessageSquareDashed className="w-5 h-5 shrink-0" />
                    <span className={\`transition-all duration-300 overflow-hidden whitespace-nowrap \${sidebarOpen ? 'max-w-[200px] opacity-100 translate-x-0' : 'max-w-0 opacity-0 -translate-x-2'}\`}>Dynamic Templates</span>
                  </button>
                  <button 
                    onClick={() => setCurrentPage('developer')} 
                    className={\`w-full flex items-center gap-4 px-4 py-3 rounded-xl font-medium transition-all \${currentPage === 'developer' ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-500/20' : 'text-gray-400 hover:bg-slate-900/60 hover:text-white'}\`}
                  >
                    <TerminalSquare className="w-5 h-5 shrink-0" />
                    <span className={\`transition-all duration-300 overflow-hidden whitespace-nowrap \${sidebarOpen ? 'max-w-[200px] opacity-100 translate-x-0' : 'max-w-0 opacity-0 -translate-x-2'}\`}>Developer APIs</span>
                  </button>`
  );
}

// 4. Insert UI screens at the bottom of the CLIENT INTERFACES block
if (!code.includes('III.X DEVELOPER API PORTAL')) {
  code = code.replace(
    /(\s*)(<\/>\s*\}\)\s*\{\/\* ========================================== \*\/\}\s*\{\/\* ========================================== \*\/\}\s*<\/div>\s*<\/main>)/,
    (match, space, rest) => {
      return space + uiFragment + '\n' + rest;
    }
  );
}

fs.writeFileSync('frontend/src/App.tsx', code);
console.log('Injection successful!');
