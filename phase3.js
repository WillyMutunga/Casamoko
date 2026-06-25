const fs = require('fs');
let code = fs.readFileSync('frontend/src/App.tsx', 'utf8');

// 1. Inject imports
code = code.replace(
  "import { Key, Copy, Trash2, Code, Library, Webhook, TerminalSquare, FileCode2, MessageSquareDashed, GitMerge, ArrowRightLeft, Route, Inbox } from 'lucide-react';",
  "import { Key, Copy, Trash2, Code, Library, Webhook, TerminalSquare, FileCode2, MessageSquareDashed, GitMerge, ArrowRightLeft, Route, Inbox, Play, Pause, XCircle } from 'lucide-react';"
);

// 2. Inject state
const lcrState = `  const [lcrRoutes] = useState([
    { id: 1, provider: 'Safaricom Direct', mcc: '639', mnc: '02', prefix: '2547', cost: 0.008, priority: 1, status: 'ACTIVE' },
    { id: 2, provider: 'RouteMobile', mcc: '639', mnc: '02', prefix: '2547', cost: 0.006, priority: 2, status: 'ACTIVE' },
    { id: 3, provider: 'Infobip Global', mcc: '*', mnc: '*', prefix: '*', cost: 0.012, priority: 99, status: 'ACTIVE' }
  ]);`;

const newState = `  // Phase 3 States
  const [isWizardOpen, setIsWizardOpen] = useState(false);`;

code = code.replace(lcrState, lcrState + "\n\n" + newState);

// 3. Rename sidebar text
code = code.replace(
  /Campaign Wizard<\/span>/,
  'Campaigns & Queue</span>'
);

// 4. Update conditional rendering
// Instead of a massive replace, I will locate the start of the wizard and the old table

const wizardStartPattern = "{currentPage === 'campaigns' && (\n                    <div className=\"space-y-8 animate-fadeIn\">\n                      \n                      <div className=\"flex items-center justify-between mb-8 pb-4 border-b border-slate-800/40\">\n                        <h3 className=\"text-lg font-bold text-white flex items-center gap-2\">\n                          <Send className=\"w-5 h-5 text-indigo-400\" />\n                          Bulk Campaign Wizard\n                        </h3>";

const wizardStartReplacement = `{currentPage === 'campaigns' && !isWizardOpen && (
                    <div className="space-y-6 animate-fadeIn">
                      <div className="flex items-center justify-between mb-8 pb-4 border-b border-slate-800/40">
                        <div>
                          <h3 className="text-xl font-bold text-white flex items-center gap-2">
                            <Send className="w-6 h-6 text-indigo-400" />
                            Queued & Scheduled Campaigns
                          </h3>
                          <p className="text-xs text-gray-400 mt-1">Monitor, pause, and review all pending SMS broadcasts.</p>
                        </div>
                        <button 
                          onClick={() => setIsWizardOpen(true)}
                          className="flex items-center gap-2 px-5 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl transition-all shadow-lg shadow-indigo-500/20 text-sm font-bold"
                        >
                          <Plus className="w-4 h-4" />
                          New Campaign
                        </button>
                      </div>

                      {/* INJECTED TABLE START */}
                      <div className="glass-panel p-6 rounded-2xl border border-slate-850 glow-card col-span-full">
                        <div className="overflow-x-auto">
                          <table className="w-full text-left text-sm text-gray-300">
                            <thead className="bg-slate-900/60 uppercase tracking-widest text-[10px] text-gray-400 font-bold">
                              <tr>
                                <th className="px-6 py-4 rounded-l-xl">Campaign Name</th>
                                <th className="px-6 py-4">Status</th>
                                <th className="px-6 py-4 text-center">TPS Limit</th>
                                <th className="px-6 py-4 text-center">Sent / Deliv</th>
                                <th className="px-6 py-4 text-center">Approvals</th>
                                <th className="px-6 py-4 rounded-r-xl text-center">Programmatic Action</th>
                              </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-800/40 font-mono text-xs">
                              {clientCampaigns.map(camp => (
                                <tr key={camp.id} className="hover:bg-slate-900/20">
                                  <td className="px-6 py-4 font-sans">
                                    <span className="font-bold text-white block">{camp.name}</span>
                                    <span className="text-[10px] text-gray-400 mt-0.5 truncate max-w-[200px] block font-mono">{camp.template}</span>
                                  </td>
                                  <td className="px-6 py-4">
                                    <span className={\`px-2 py-0.5 rounded text-[10px] font-bold uppercase \${
                                      camp.status === 'COMPLETED' ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' :
                                      camp.status === 'PROCESSING' ? 'bg-indigo-500/10 text-indigo-400 border border-indigo-500/20 animate-pulse' :
                                      camp.status === 'PAUSED' ? 'bg-amber-500/10 text-amber-400 border border-amber-500/20' :
                                      camp.status === 'CANCELED' ? 'bg-red-500/10 text-red-400 border border-red-500/20' :
                                      'bg-slate-500/10 text-gray-400 border border-slate-500/20'
                                    }\`}>
                                      {camp.status}
                                    </span>
                                  </td>
                                  <td className="px-6 py-4 text-center font-mono font-bold text-xs">{camp.tps_limit} TPS</td>
                                  <td className="px-6 py-4 text-center font-mono text-xs">
                                    <b className="text-white">{camp.sent_count}</b> / <b className="text-emerald-450">{camp.delivered_count}</b>
                                  </td>
                                  <td className="px-6 py-4 text-center">
                                    <span className={\`px-2 py-0.5 rounded text-[9px] font-bold \${
                                      camp.approval_status === 'APPROVED' ? 'bg-emerald-500/10 text-emerald-450' : 'bg-amber-500/10 text-amber-450 animate-pulse'
                                    }\`}>
                                      {camp.approval_status}
                                    </span>
                                  </td>
                                  <td className="px-6 py-4 flex gap-2 justify-center">
                                    <button
                                      onClick={async () => {
                                        if (token) {
                                          try {
                                            const res = await apiClient.post(\`/campaigns/\${camp.id}/duplicate\`, {}, { headers: { Authorization: \`Bearer \${token}\` } });
                                            if (res.data.status === 'SUCCESS') fetchClientData(token);
                                          } catch (err) {}
                                        } else {
                                          const clone = { ...camp, id: Date.now(), name: camp.name + ' (Copy)', status: 'DRAFT' };
                                          setClientCampaigns([clone, ...clientCampaigns]);
                                        }
                                      }}
                                      className="px-2.5 py-1 bg-slate-900 border border-slate-800 hover:bg-slate-800 text-indigo-400 font-bold text-xs rounded-lg transition-all font-sans"
                                    >
                                      Clone
                                    </button>

                                    {camp.status === 'PROCESSING' && (
                                      <button
                                        onClick={async () => {
                                          if (token) {
                                            await apiClient.post(\`/campaigns/\${camp.id}/action\`, { action: 'PAUSE' }, { headers: { Authorization: \`Bearer \${token}\` } });
                                            fetchClientData(token);
                                          } else {
                                            const updated = clientCampaigns.map(c => c.id === camp.id ? { ...c, status: 'PAUSED' } : c);
                                            setClientCampaigns(updated);
                                          }
                                        }}
                                        className="px-2.5 py-1 bg-amber-600 hover:bg-amber-700 text-white font-bold text-xs rounded-lg transition-all font-sans"
                                      >
                                        Pause
                                      </button>
                                    )}
                                    {camp.status === 'PAUSED' && (
                                      <button
                                        onClick={async () => {
                                          if (token) {
                                            await apiClient.post(\`/campaigns/\${camp.id}/action\`, { action: 'RESUME' }, { headers: { Authorization: \`Bearer \${token}\` } });
                                            fetchClientData(token);
                                          } else {
                                            const updated = clientCampaigns.map(c => c.id === camp.id ? { ...c, status: 'PROCESSING' } : c);
                                            setClientCampaigns(updated);
                                          }
                                        }}
                                        className="px-2.5 py-1 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs rounded-lg transition-all font-sans"
                                      >
                                        Resume
                                      </button>
                                    )}
                                    {['PAUSED', 'PROCESSING'].includes(camp.status) && (
                                      <button
                                        onClick={() => {
                                          const updated = clientCampaigns.map(c => c.id === camp.id ? { ...c, status: 'CANCELED' } : c);
                                          setClientCampaigns(updated);
                                        }}
                                        className="px-2.5 py-1 bg-red-600 hover:bg-red-700 text-white font-bold text-xs rounded-lg transition-all font-sans"
                                      >
                                        Cancel
                                      </button>
                                    )}
                                  </td>
                                </tr>
                              ))}
                            </tbody>
                          </table>
                        </div>
                      </div>
                      {/* INJECTED TABLE END */}
                    </div>
                  )}

                  {currentPage === 'campaigns' && isWizardOpen && (
                    <div className="space-y-8 animate-fadeIn">
                      
                      <div className="flex items-center justify-between mb-8 pb-4 border-b border-slate-800/40">
                        <div className="flex flex-col gap-2">
                          <button 
                            onClick={() => setIsWizardOpen(false)}
                            className="flex items-center gap-1 text-xs font-semibold text-gray-400 hover:text-white transition-all w-fit"
                          >
                            <ArrowDownLeft className="w-4 h-4" />
                            Back to Dashboard
                          </button>
                          <h3 className="text-lg font-bold text-white flex items-center gap-2">
                            <Send className="w-5 h-5 text-indigo-400" />
                            Bulk Campaign Wizard
                          </h3>
                        </div>`;

code = code.replace(wizardStartPattern, wizardStartReplacement);

// 5. Delete the old table
const oldTableStart = '<div className="glass-panel p-6 rounded-2xl border border-slate-850 glow-card col-span-full mt-8 animate-fadeIn">\n                    <h4 className="font-bold text-white mb-4 flex items-center gap-2">\n                      <Send className="w-5 h-5 text-indigo-400" />\n                      Bulk Campaigns History Registry\n                    </h4>';
const oldTableEnd = '                  </div>\n\n                </div>\n              )}';
let idx1 = code.indexOf(oldTableStart);
let idx2 = code.indexOf(oldTableEnd);
if (idx1 !== -1 && idx2 !== -1) {
  code = code.substring(0, idx1) + code.substring(idx2 + '                  </div>'.length);
} else {
  console.log("Warning: Old table not cleanly removed.");
}

// 6. Update handleLaunchCampaign to close wizard
code = code.replace(
  'onClick={handleLaunchCampaign}',
  'onClick={() => { handleLaunchCampaign(); setIsWizardOpen(false); }}'
);

fs.writeFileSync('frontend/src/App.tsx', code);
console.log('App.tsx phase 3 migration complete!');
