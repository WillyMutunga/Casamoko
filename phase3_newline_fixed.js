const fs = require('fs');
let code = fs.readFileSync('frontend/src/App.tsx', 'utf8').replace(/\r\n/g, '\n');

// 1. Rename sidebar text
code = code.replace(
  /Campaign Wizard<\/span>/,
  'Campaigns & Queue</span>'
);

// 2. Add isWizardOpen state
if (!code.includes('const [isWizardOpen, setIsWizardOpen] = useState(false);')) {
  const target = "  // Client Campaigns List State";
  const replacement = `  // Phase 3 States\n  const [isWizardOpen, setIsWizardOpen] = useState(false);\n\n  // Client Campaigns List State`;
  code = code.replace(target, replacement);
}

// 3. Extract old table
const oldTableStart = '<div className="glass-panel p-6 rounded-2xl border border-slate-850 glow-card col-span-full mt-8 animate-fadeIn">\n                    <h4 className="font-bold text-white mb-4 flex items-center gap-2">\n                      <Send className="w-5 h-5 text-indigo-400" />\n                      Bulk Campaigns History Registry\n                    </h4>';
const oldTableEnd = '                  </div>\n\n                </div>\n              )}';
let idx1 = code.indexOf(oldTableStart);
let idx2 = code.indexOf(oldTableEnd);
let oldTable = '';
if (idx1 !== -1 && idx2 !== -1) {
  oldTable = code.substring(idx1, idx2 + '                  </div>'.length);
  code = code.substring(0, idx1) + code.substring(idx2 + '                  </div>'.length);
} else {
  console.log("Could not find old table boundaries");
  process.exit(1);
}

// 4. Inject new layout
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

                      {/* OLD TABLE REINJECTED */}
                      ` + oldTable.replace('mt-8 animate-fadeIn', '') + `
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

// 5. Close wizard on launch
code = code.replace(
  'onClick={handleLaunchCampaign}',
  'onClick={() => { handleLaunchCampaign(); setIsWizardOpen(false); }}'
);

// We need to make sure ArrowDownLeft and Plus are imported.
if (!code.includes('ArrowDownLeft')) {
  code = code.replace(/import \{ Clock, Send/, "import { ArrowDownLeft, Clock, Send");
}
if (!code.includes('Plus')) {
  code = code.replace(/import \{ Clock, Send/, "import { Plus, Clock, Send");
}

fs.writeFileSync('frontend/src/App.tsx', code);
console.log('App.tsx phase 3 script complete!');
