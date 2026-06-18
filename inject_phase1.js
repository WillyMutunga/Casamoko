const fs = require('fs');

let code = fs.readFileSync('frontend/src/App.tsx', 'utf8');

// 1. Add missing lucide-react icons
code = code.replace(/import \{([^\}]+)\} from 'lucide-react';/, (match, group1) => {
  if (!group1.includes('Webhook')) {
    return `import {${group1}, Webhook, TerminalSquare, FileCode2, MessageSquareDashed } from 'lucide-react';`;
  }
  return match;
});

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
if (!code.includes('setCurrentPage(\'developer\')')) {
  code = code.replace(
    /<button \n\s*onClick=\{.*?setCurrentPage\('sender_ids'\)[\s\S]*?<\/button>/,
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
const uiBlock = `
                  {/* III.X DEVELOPER API PORTAL */}
                  {currentPage === 'developer' && (
                    <div className="space-y-8 animate-fade-in-up">
                      <div className="flex items-center gap-4 border-b border-slate-800/60 pb-6">
                        <div className="w-12 h-12 rounded-xl bg-indigo-500/10 flex items-center justify-center border border-indigo-500/20">
                          <TerminalSquare className="w-6 h-6 text-indigo-400" />
                        </div>
                        <div>
                          <h2 className="text-2xl font-black text-white tracking-wider">Developer API Settings</h2>
                          <p className="text-sm text-gray-400 mt-1">Generate API Keys, setup Delivery Webhooks, and integrate CPaaS.</p>
                        </div>
                      </div>

                      <div className="grid grid-cols-1 lg:grid-cols-2 gap-8">
                        {/* API Key Box */}
                        <div className="glass-panel p-8 rounded-2xl border border-slate-800/80">
                          <h3 className="text-lg font-bold text-white mb-4 flex items-center gap-2">
                            <Key className="w-5 h-5 text-indigo-400" /> Authentication Token
                          </h3>
                          <p className="text-sm text-gray-400 mb-6">Use this Bearer Token to authenticate against the Casamoko REST API.</p>
                          <div className="bg-slate-950 border border-slate-800 p-4 rounded-xl flex items-center justify-between">
                            <code className="text-emerald-400 font-mono text-xs">{devApiKey}</code>
                            <button onClick={() => toast.success('API Key copied to clipboard')} className="text-gray-400 hover:text-white">
                              <Copy className="w-4 h-4" />
                            </button>
                          </div>
                          <button onClick={() => {
                            setDevApiKey('live_csmk_' + Math.random().toString(36).substring(2, 26));
                            toast.success('Regenerated API Key! Update your servers immediately.');
                          }} className="mt-6 w-full py-3 bg-slate-900 hover:bg-slate-800 border border-slate-700 text-white rounded-xl text-xs font-bold uppercase tracking-wider transition-all">
                            Regenerate Key
                          </button>
                        </div>

                        {/* Webhook Box */}
                        <div className="glass-panel p-8 rounded-2xl border border-slate-800/80">
                          <h3 className="text-lg font-bold text-white mb-4 flex items-center gap-2">
                            <Webhook className="w-5 h-5 text-indigo-400" /> Delivery Webhook (DLR)
                          </h3>
                          <p className="text-sm text-gray-400 mb-6">Receive real-time HTTP POST callbacks when a message is Delivered or Failed.</p>
                          <label className="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">Endpoint URL</label>
                          <input 
                            type="url"
                            value={devWebhookUrl}
                            onChange={(e) => setDevWebhookUrl(e.target.value)}
                            className="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:ring-1 focus:ring-indigo-500 font-mono"
                            placeholder="https://api.yourserver.com/webhooks/sms"
                          />
                          <button onClick={() => toast.success('Webhook endpoint secured!')} className="mt-6 w-full py-3 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-bold uppercase tracking-wider transition-all shadow-[0_0_20px_rgba(79,70,229,0.3)]">
                            Save Webhook Config
                          </button>
                        </div>
                      </div>

                      {/* Documentation Snippet */}
                      <div className="glass-panel p-8 rounded-2xl border border-slate-800/80">
                        <h3 className="text-lg font-bold text-white mb-4 flex items-center gap-2">
                          <FileCode2 className="w-5 h-5 text-indigo-400" /> Send SMS API Snippet (cURL)
                        </h3>
                        <div className="bg-slate-950 p-6 rounded-xl border border-slate-800 font-mono text-xs overflow-x-auto text-gray-300">
                          <pre>{'curl -X POST https://api.casamoko.com/v1/sms/send \\\\'}
{`  -H 'Authorization: Bearer \${devApiKey}' \\\\`}
{'  -H \'Content-Type: application/json\' \\\\'}
{'  -d \'{'}
{'    "to": ["+254700000000"],'}
{'    "sender_id": "YOUR_SENDER",'}
{'    "message": "Hello from Casamoko API!"'}
{'  }\''}
                          </pre>
                        </div>
                      </div>
                    </div>
                  )}

                  {/* III.Y DYNAMIC TEMPLATES MANAGER */}
                  {currentPage === 'templates' && (
                    <div className="space-y-8 animate-fade-in-up">
                      <div className="flex items-center gap-4 border-b border-slate-800/60 pb-6">
                        <div className="w-12 h-12 rounded-xl bg-emerald-500/10 flex items-center justify-center border border-emerald-500/20">
                          <MessageSquareDashed className="w-6 h-6 text-emerald-400" />
                        </div>
                        <div>
                          <h2 className="text-2xl font-black text-white tracking-wider">Templates Manager</h2>
                          <p className="text-sm text-gray-400 mt-1">Create dynamic message templates with {`{variables}`} for highly personalized campaigns.</p>
                        </div>
                      </div>

                      <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
                        <div className="lg:col-span-1 space-y-6">
                          <div className="glass-panel p-6 rounded-2xl border border-slate-800/80">
                            <h3 className="text-sm font-bold text-white uppercase tracking-wider mb-6">Create New Template</h3>
                            <div className="space-y-4">
                              <div>
                                <label className="block text-xs font-semibold text-gray-400 mb-2">Template Name</label>
                                <input 
                                  value={newTemplateName}
                                  onChange={(e) => setNewTemplateName(e.target.value)}
                                  placeholder="e.g. Invoice Reminder"
                                  className="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-2.5 text-sm text-white focus:ring-1 focus:ring-emerald-500"
                                />
                              </div>
                              <div>
                                <label className="block text-xs font-semibold text-gray-400 mb-2">Content (Use {`{var}`} for variables)</label>
                                <textarea 
                                  value={newTemplateContent}
                                  onChange={(e) => setNewTemplateContent(e.target.value)}
                                  rows={4}
                                  placeholder="Hi {name}, your balance is {amount}."
                                  className="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-3 text-sm text-white focus:ring-1 focus:ring-emerald-500"
                                />
                              </div>
                              <button onClick={() => {
                                if(!newTemplateName || !newTemplateContent) return;
                                setTemplatesList([...templatesList, { id: Date.now(), name: newTemplateName, content: newTemplateContent, status: 'PENDING' }]);
                                setNewTemplateName('');
                                setNewTemplateContent('');
                                toast.success('Template submitted for carrier approval.');
                              }} className="w-full py-3 bg-emerald-600 hover:bg-emerald-500 text-slate-900 rounded-xl text-xs font-bold uppercase tracking-wider shadow-[0_0_20px_rgba(16,185,129,0.3)]">
                                Submit Template
                              </button>
                            </div>
                          </div>
                        </div>

                        <div className="lg:col-span-2">
                          <div className="glass-panel rounded-2xl border border-slate-800/80 overflow-hidden">
                            <table className="w-full text-left border-collapse">
                              <thead>
                                <tr className="bg-slate-900/50 border-b border-slate-800">
                                  <th className="px-6 py-4 text-xs font-bold text-gray-400 uppercase tracking-wider">Template Name</th>
                                  <th className="px-6 py-4 text-xs font-bold text-gray-400 uppercase tracking-wider">Content Preview</th>
                                  <th className="px-6 py-4 text-xs font-bold text-gray-400 uppercase tracking-wider">Status</th>
                                </tr>
                              </thead>
                              <tbody className="divide-y divide-slate-800/50">
                                {templatesList.map(t => (
                                  <tr key={t.id} className="hover:bg-slate-800/20 transition-colors">
                                    <td className="px-6 py-4 text-sm font-bold text-white">{t.name}</td>
                                    <td className="px-6 py-4 text-xs text-gray-400 font-mono">
                                      {t.content.length > 40 ? t.content.substring(0, 40) + '...' : t.content}
                                    </td>
                                    <td className="px-6 py-4">
                                      <span className={\`px-3 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider \${t.status === 'APPROVED' ? 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/30' : 'bg-amber-500/20 text-amber-400 border border-amber-500/30'}\`}>
                                        {t.status}
                                      </span>
                                    </td>
                                  </tr>
                                ))}
                                {templatesList.length === 0 && (
                                  <tr>
                                    <td colSpan={3} className="px-6 py-12 text-center text-gray-500 text-sm">No dynamic templates created yet.</td>
                                  </tr>
                                )}
                              </tbody>
                            </table>
                          </div>
                        </div>
                      </div>
                    </div>
                  )}
`;

if (!code.includes('III.X DEVELOPER API PORTAL')) {
  // Find a good place to insert. Right before `</> // END OF CLIENT ACCOUNT WRAPPER` 
  code = code.replace(
    /(\s*)(<\/>\s*\}\)\s*\{\/\* ========================================== \*\/\}\s*\{\/\* ========================================== \*\/\}\s*<\/div>\s*<\/main>)/,
    (match, space, rest) => {
      return space + uiBlock + rest;
    }
  );
}

fs.writeFileSync('frontend/src/App.tsx', code);
console.log("Injection completed");
