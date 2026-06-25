const fs = require('fs');
let code = fs.readFileSync('frontend/src/App.tsx', 'utf8');

const startSearch = '<div className="glass-panel p-6 rounded-2xl border border-slate-850 glow-card col-span-full mt-8 animate-fadeIn">';
const endSearch = '                  </div>\n\n                </div>\n              )}\n\n              {/* III.3 CLIENT CONTACTS';

let startIndex = code.indexOf(startSearch);
let endIndex = code.indexOf(endSearch);

if (startIndex === -1 || endIndex === -1) {
  console.log('Could not find boundaries.', startIndex, endIndex);
  process.exit(1);
}

let tableStr = code.substring(startIndex, endIndex + '                  </div>'.length);

code = code.substring(0, startIndex) + code.substring(endIndex + '                  </div>'.length);

let newDashboardUI = `                  {currentPage === 'campaigns' && !isWizardOpen && (
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
` + tableStr + `
                    </div>
                  )}

                  {currentPage === 'campaigns' && isWizardOpen && (
                    <div className="space-y-8 animate-fadeIn">`;

code = code.replace(
  /\{\s*currentPage\s*===\s*'campaigns'\s*&&\s*\(\s*<div className="space-y-8 animate-fadeIn">/,
  newDashboardUI
);

code = code.replace(
  /<h3 className="text-lg font-bold text-white flex items-center gap-2">\s*<Send className="w-5 h-5 text-indigo-400" \/>\s*Bulk Campaign Wizard\s*<\/h3>/,
  `<div className="flex flex-col gap-2">
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
                        </div>`
);

code = code.replace(
  /onClick=\{handleLaunchCampaign\}/,
  `onClick={() => { handleLaunchCampaign(); setIsWizardOpen(false); }}`
);

fs.writeFileSync('frontend/src/App.tsx', code);
console.log('Extraction and relocation completed!');
