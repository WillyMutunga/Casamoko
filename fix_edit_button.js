const fs = require('fs');
let code = fs.readFileSync('frontend/src/App.tsx', 'utf8').replace(/\r\n/g, '\n');

// 1. Add setLcrRoutes and routeFormData state
code = code.replace(
  'const [lcrRoutes] = useState([',
  'const [lcrRoutes, setLcrRoutes] = useState(['
);

if (!code.includes('const [routeFormData, setRouteFormData] = useState<any>({});')) {
  code = code.replace(
    'const [editingRoute, setEditingRoute] = useState<any>(null);',
    'const [editingRoute, setEditingRoute] = useState<any>(null);\n  const [routeFormData, setRouteFormData] = useState<any>({});'
  );
}

// 2. Fix Edit button onClick
code = code.replace(
  /onClick=\{\(\) => \{ setEditingRoute\(r\); setIsRouteModalOpen\(true\); \}\}/g,
  `onClick={() => { setEditingRoute(r); setRouteFormData({...r}); setIsRouteModalOpen(true); }}`
);

// 3. Fix Add Route button onClick
code = code.replace(
  /onClick=\{\(\) => \{ setEditingRoute\(null\); setIsRouteModalOpen\(true\); \}\}/g,
  `onClick={() => { setEditingRoute(null); setRouteFormData({ provider: '', mcc: '', mnc: '', prefix: '', cost: 0.000, priority: 1, status: 'ACTIVE' }); setIsRouteModalOpen(true); }}`
);

// 4. Replace the Modal JSX with controlled inputs and save logic
const oldModal = `{isRouteModalOpen && (
                        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm animate-fadeIn">
                          <div className="bg-slate-900 border border-slate-800 rounded-2xl p-6 w-full max-w-md shadow-2xl">
                            <div className="flex justify-between items-center mb-6">
                              <h3 className="text-xl font-bold text-white flex items-center gap-2">
                                <Route className="w-5 h-5 text-indigo-400" />
                                {editingRoute ? 'Edit LCR Route' : 'Add LCR Route'}
                              </h3>
                              <button onClick={() => setIsRouteModalOpen(false)} className="text-gray-400 hover:text-white">
                                <X className="w-5 h-5" />
                              </button>
                            </div>
                            
                            <div className="space-y-4">
                              <div>
                                <label className="block text-xs font-bold text-gray-400 mb-1">Provider Connection</label>
                                <select className="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-2 text-sm text-white focus:outline-none focus:border-indigo-500" defaultValue={editingRoute?.provider || ''}>
                                  <option value="">Select Connection Bind...</option>
                                  <option value="Safaricom Direct">Safaricom Direct</option>
                                  <option value="RouteMobile">RouteMobile</option>
                                  <option value="Infobip Global">Infobip Global</option>
                                  <option value="Twilio">Twilio</option>
                                </select>
                              </div>
                              
                              <div className="grid grid-cols-2 gap-4">
                                <div>
                                  <label className="block text-xs font-bold text-gray-400 mb-1">MCC (Country)</label>
                                  <input type="text" defaultValue={editingRoute?.mcc || ''} placeholder="e.g. 639" className="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-2 text-sm text-white focus:outline-none focus:border-indigo-500" />
                                </div>
                                <div>
                                  <label className="block text-xs font-bold text-gray-400 mb-1">MNC (Network)</label>
                                  <input type="text" defaultValue={editingRoute?.mnc || ''} placeholder="e.g. 02" className="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-2 text-sm text-white focus:outline-none focus:border-indigo-500" />
                                </div>
                              </div>

                              <div className="grid grid-cols-2 gap-4">
                                <div>
                                  <label className="block text-xs font-bold text-gray-400 mb-1">Prefix / Regex</label>
                                  <input type="text" defaultValue={editingRoute?.prefix || ''} placeholder="e.g. 2547" className="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-2 text-sm text-white focus:outline-none focus:border-indigo-500" />
                                </div>
                                <div>
                                  <label className="block text-xs font-bold text-indigo-400 mb-1">Base Cost per SMS (Ksh)</label>
                                  <input type="number" step="0.001" defaultValue={editingRoute?.cost || ''} placeholder="0.000" className="w-full bg-slate-950 border border-indigo-500/50 rounded-xl px-4 py-2 text-sm text-white font-mono focus:outline-none focus:border-indigo-400 shadow-[0_0_10px_rgba(79,70,229,0.1)]" />
                                </div>
                              </div>

                              <div className="grid grid-cols-2 gap-4">
                                <div>
                                  <label className="block text-xs font-bold text-gray-400 mb-1">LCR Priority (1 = Highest)</label>
                                  <input type="number" defaultValue={editingRoute?.priority || '1'} className="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-2 text-sm text-white focus:outline-none focus:border-indigo-500" />
                                </div>
                                <div>
                                  <label className="block text-xs font-bold text-gray-400 mb-1">Status</label>
                                  <select className="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-2 text-sm text-white focus:outline-none focus:border-indigo-500" defaultValue={editingRoute?.status || 'ACTIVE'}>
                                    <option value="ACTIVE">ACTIVE</option>
                                    <option value="INACTIVE">INACTIVE</option>
                                    <option value="FAILOVER_ONLY">FAILOVER_ONLY</option>
                                  </select>
                                </div>
                              </div>
                            </div>

                            <div className="mt-8 flex justify-end gap-3">
                              <button onClick={() => setIsRouteModalOpen(false)} className="px-4 py-2 text-sm font-bold text-gray-400 hover:text-white transition-all">
                                Cancel
                              </button>
                              <button onClick={() => setIsRouteModalOpen(false)} className="px-6 py-2 bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-bold rounded-xl shadow-lg shadow-indigo-500/20 transition-all">
                                Save Configuration
                              </button>
                            </div>
                          </div>
                        </div>
                      )}`;

const newModal = `{isRouteModalOpen && (
                        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm animate-fadeIn">
                          <div className="bg-slate-900 border border-slate-800 rounded-2xl p-6 w-full max-w-md shadow-2xl">
                            <div className="flex justify-between items-center mb-6">
                              <h3 className="text-xl font-bold text-white flex items-center gap-2">
                                <Route className="w-5 h-5 text-indigo-400" />
                                {editingRoute ? 'Edit LCR Route' : 'Add LCR Route'}
                              </h3>
                              <button onClick={() => setIsRouteModalOpen(false)} className="text-gray-400 hover:text-white">
                                <X className="w-5 h-5" />
                              </button>
                            </div>
                            
                            <div className="space-y-4">
                              <div>
                                <label className="block text-xs font-bold text-gray-400 mb-1">Provider Connection</label>
                                <select className="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-2 text-sm text-white focus:outline-none focus:border-indigo-500" value={routeFormData.provider || ''} onChange={(e) => setRouteFormData({...routeFormData, provider: e.target.value})}>
                                  <option value="">Select Connection Bind...</option>
                                  <option value="Safaricom Direct">Safaricom Direct</option>
                                  <option value="RouteMobile">RouteMobile</option>
                                  <option value="Infobip Global">Infobip Global</option>
                                  <option value="Twilio">Twilio</option>
                                </select>
                              </div>
                              
                              <div className="grid grid-cols-2 gap-4">
                                <div>
                                  <label className="block text-xs font-bold text-gray-400 mb-1">MCC (Country)</label>
                                  <input type="text" value={routeFormData.mcc || ''} onChange={(e) => setRouteFormData({...routeFormData, mcc: e.target.value})} placeholder="e.g. 639" className="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-2 text-sm text-white focus:outline-none focus:border-indigo-500" />
                                </div>
                                <div>
                                  <label className="block text-xs font-bold text-gray-400 mb-1">MNC (Network)</label>
                                  <input type="text" value={routeFormData.mnc || ''} onChange={(e) => setRouteFormData({...routeFormData, mnc: e.target.value})} placeholder="e.g. 02" className="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-2 text-sm text-white focus:outline-none focus:border-indigo-500" />
                                </div>
                              </div>

                              <div className="grid grid-cols-2 gap-4">
                                <div>
                                  <label className="block text-xs font-bold text-gray-400 mb-1">Prefix / Regex</label>
                                  <input type="text" value={routeFormData.prefix || ''} onChange={(e) => setRouteFormData({...routeFormData, prefix: e.target.value})} placeholder="e.g. 2547" className="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-2 text-sm text-white focus:outline-none focus:border-indigo-500" />
                                </div>
                                <div>
                                  <label className="block text-xs font-bold text-indigo-400 mb-1">Base Cost per SMS (Ksh)</label>
                                  <input type="number" step="0.001" value={routeFormData.cost || ''} onChange={(e) => setRouteFormData({...routeFormData, cost: parseFloat(e.target.value)})} placeholder="0.000" className="w-full bg-slate-950 border border-indigo-500/50 rounded-xl px-4 py-2 text-sm text-white font-mono focus:outline-none focus:border-indigo-400 shadow-[0_0_10px_rgba(79,70,229,0.1)]" />
                                </div>
                              </div>

                              <div className="grid grid-cols-2 gap-4">
                                <div>
                                  <label className="block text-xs font-bold text-gray-400 mb-1">LCR Priority (1 = Highest)</label>
                                  <input type="number" value={routeFormData.priority || '1'} onChange={(e) => setRouteFormData({...routeFormData, priority: parseInt(e.target.value)})} className="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-2 text-sm text-white focus:outline-none focus:border-indigo-500" />
                                </div>
                                <div>
                                  <label className="block text-xs font-bold text-gray-400 mb-1">Status</label>
                                  <select className="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-2 text-sm text-white focus:outline-none focus:border-indigo-500" value={routeFormData.status || 'ACTIVE'} onChange={(e) => setRouteFormData({...routeFormData, status: e.target.value})}>
                                    <option value="ACTIVE">ACTIVE</option>
                                    <option value="INACTIVE">INACTIVE</option>
                                    <option value="FAILOVER_ONLY">FAILOVER_ONLY</option>
                                  </select>
                                </div>
                              </div>
                            </div>

                            <div className="mt-8 flex justify-end gap-3">
                              <button onClick={() => setIsRouteModalOpen(false)} className="px-4 py-2 text-sm font-bold text-gray-400 hover:text-white transition-all">
                                Cancel
                              </button>
                              <button onClick={() => {
                                if (editingRoute) {
                                  setLcrRoutes(lcrRoutes.map(r => r.id === editingRoute.id ? routeFormData : r));
                                } else {
                                  setLcrRoutes([...lcrRoutes, { ...routeFormData, id: Math.max(...lcrRoutes.map(r => r.id)) + 1 }]);
                                }
                                setIsRouteModalOpen(false);
                              }} className="px-6 py-2 bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-bold rounded-xl shadow-lg shadow-indigo-500/20 transition-all">
                                Save Configuration
                              </button>
                            </div>
                          </div>
                        </div>
                      )}`;

code = code.replace(oldModal, newModal);
fs.writeFileSync('frontend/src/App.tsx', code);
console.log('App.tsx edit button fix complete!');
