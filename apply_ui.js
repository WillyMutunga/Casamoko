const fs = require('fs');
let content = fs.readFileSync('frontend/src/App.tsx', 'utf8');

// 1. Add X icon
content = content.replace(
`  ArrowDownLeft,
  Sliders
} from 'lucide-react';`,
`  ArrowDownLeft,
  Sliders,
  X
} from 'lucide-react';`
);

// 2. Add showAuthModal state
content = content.replace(
`  // Auth Form State
  const [email, setEmail] = useState('');`,
`  // Auth Form State
  const [showAuthModal, setShowAuthModal] = useState(false);
  const [email, setEmail] = useState('');`
);

// 3. Replace Auth Screen
const authScreenNewJSX = `      {/* 1. LANDING PAGE & AUTH MODAL */}
      {!isLoggedIn && (
        <div className="flex-1 flex flex-col relative overflow-hidden bg-slate-950 min-h-screen text-slate-200 font-sans">
          {/* Landing Page Background FX */}
          <div className="absolute top-[-10%] left-[-10%] w-[50%] h-[50%] rounded-full bg-indigo-600/10 blur-[120px] mix-blend-screen pointer-events-none"></div>
          <div className="absolute bottom-[-10%] right-[-10%] w-[50%] h-[50%] rounded-full bg-blue-600/10 blur-[120px] mix-blend-screen pointer-events-none"></div>

          {/* Header */}
          <header className="fixed top-0 w-full z-40 border-b border-white/5 bg-slate-950/50 backdrop-blur-md">
            <div className="max-w-7xl mx-auto px-6 h-20 flex items-center justify-between">
              <div className="flex items-center gap-3">
                <div className="w-10 h-10 rounded-xl bg-gradient-to-tr from-indigo-600 to-blue-500 flex items-center justify-center shadow-lg shadow-indigo-500/20">
                  <MessageSquare className="w-5 h-5 text-white" />
                </div>
                <span className="font-extrabold text-xl tracking-widest uppercase text-white">Casamoko</span>
              </div>
              <div className="flex items-center gap-4">
                <button 
                  onClick={() => setShowAuthModal(true)}
                  className="px-6 py-2.5 text-sm font-semibold text-slate-300 hover:text-white transition-colors"
                >
                  Log in
                </button>
                <button 
                  onClick={() => { setShowAuthModal(true); setIsRegistering(true); }}
                  className="px-6 py-2.5 text-sm font-bold bg-white text-slate-900 hover:bg-slate-100 rounded-lg shadow-lg shadow-white/10 transition-all active:scale-95"
                >
                  Get Started
                </button>
              </div>
            </div>
          </header>

          {/* Hero Section */}
          <main className="flex-1 flex flex-col items-center justify-center pt-32 pb-20 px-6 z-10 text-center">
            <div className="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-indigo-500/10 border border-indigo-500/20 text-indigo-400 text-sm font-medium mb-8 animate-fade-in">
              <span className="relative flex h-2 w-2">
                <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-indigo-400 opacity-75"></span>
                <span className="relative inline-flex rounded-full h-2 w-2 bg-indigo-500"></span>
              </span>
              Enterprise Messaging Platform 2.0
            </div>
            
            <h1 className="text-5xl md:text-7xl font-extrabold tracking-tight mb-8 max-w-4xl leading-tight">
              Scale Your Communications with <br />
              <span className="text-transparent bg-clip-text bg-gradient-to-r from-indigo-400 via-blue-400 to-indigo-300">
                Precision & Speed.
              </span>
            </h1>
            
            <p className="text-lg md:text-xl text-slate-400 max-w-2xl mb-12 leading-relaxed">
              Casamoko provides high-throughput SMS delivery, robust contact management, and granular real-time billing for enterprise clients and resellers.
            </p>
            
            <div className="flex flex-col sm:flex-row items-center gap-4">
              <button 
                onClick={() => { setShowAuthModal(true); setIsRegistering(true); }}
                className="px-8 py-4 text-base font-bold bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl shadow-lg shadow-indigo-600/20 transition-all hover:scale-105 active:scale-95 w-full sm:w-auto"
              >
                Start Free Trial
              </button>
              <button 
                className="px-8 py-4 text-base font-semibold bg-slate-900 hover:bg-slate-800 border border-slate-800 text-white rounded-xl transition-all w-full sm:w-auto"
              >
                View Documentation
              </button>
            </div>

            {/* Features Grid */}
            <div className="grid grid-cols-1 md:grid-cols-3 gap-6 w-full max-w-6xl mt-32 text-left">
              {[
                { title: 'High Throughput API', desc: 'Deliver millions of messages reliably with our horizontally scaled API infrastructure.', icon: <Activity className="w-8 h-8 text-blue-400" /> },
                { title: 'Intelligent Routing', desc: 'Smart algorithms ensure the highest delivery rates across global MNO networks.', icon: <Cpu className="w-8 h-8 text-indigo-400" /> },
                { title: 'Secure Billing Engine', desc: 'Real-time wallet deductions and reseller margin management built-in.', icon: <Wallet className="w-8 h-8 text-purple-400" /> }
              ].map((f, i) => (
                <div key={i} className="p-8 rounded-2xl bg-slate-900/50 border border-slate-800 backdrop-blur-sm hover:border-slate-700 transition-colors">
                  <div className="mb-4">{f.icon}</div>
                  <h3 className="text-xl font-bold text-white mb-2">{f.title}</h3>
                  <p className="text-slate-400 leading-relaxed">{f.desc}</p>
                </div>
              ))}
            </div>
          </main>

          {/* Auth Modal Overlay */}
          {showAuthModal && (
            <div className="fixed inset-0 z-50 flex flex-col justify-center items-center p-6 bg-slate-950/80 backdrop-blur-lg overflow-y-auto">
              {/* Close Button */}
              <button 
                onClick={() => setShowAuthModal(false)}
                className="absolute top-8 right-8 p-3 rounded-full bg-slate-900 text-slate-400 hover:text-white hover:bg-slate-800 transition-all border border-slate-800 z-50"
              >
                <X className="w-6 h-6" />
              </button>

              <div className="w-full max-w-md glass-panel p-8 rounded-2xl border border-slate-800 shadow-glass relative z-10 glow-card my-auto">
                <div className="text-center mb-8">
                  <div className="inline-flex items-center justify-center p-4 bg-indigo-600/20 rounded-2xl mb-4 border border-indigo-500/30">
                    <MessageSquare className="w-8 h-8 text-indigo-400" />
                  </div>
                  <h2 className="text-3xl font-extrabold tracking-tight text-white mb-2">
                    {isRegistering ? 'Create Account' : 'Welcome Back'}
                  </h2>
                  <p className="text-gray-400 text-sm">
                    {isRegistering ? 'Register for a new corporate client account' : 'Sign in to access your Casamoko workspace'}
                  </p>
                </div>

                {authError && (
                  <div className="p-4 bg-red-900/30 border border-red-500/40 rounded-xl mb-6 flex items-start gap-3">
                    <AlertTriangle className="w-5 h-5 text-red-400 shrink-0 mt-0.5" />
                    <span className="text-red-300 text-sm font-medium">{authError}</span>
                  </div>
                )}

                {isRegistering ? (
                  <form onSubmit={handleRegister} className="space-y-4">
                    <div className="p-3 bg-indigo-950/40 border border-indigo-500/20 text-indigo-300 text-xs rounded-xl font-medium">
                      📋 Register a new corporate client account. Reseller accounts are provisioned by the system administrator.
                    </div>
                    <div>
                      <label className="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">Email Address</label>
                      <input 
                        type="email" 
                        required 
                        value={regEmail}
                        onChange={(e) => setRegEmail(e.target.value)}
                        className="w-full bg-slate-900/80 border border-slate-800 rounded-xl px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all"
                        placeholder="admin@brand.com"
                      />
                    </div>
                    <div>
                      <label className="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">Password</label>
                      <div className="relative">
                        <input 
                          type={showRegPassword ? "text" : "password"} 
                          required 
                          value={regPassword}
                          onChange={(e) => setRegPassword(e.target.value)}
                          className="w-full bg-slate-900/80 border border-slate-800 rounded-xl pl-4 pr-12 py-3 text-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all"
                          placeholder="••••••••"
                        />
                        <button
                          type="button"
                          onClick={() => setShowRegPassword(!showRegPassword)}
                          className="absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 hover:text-white transition-colors"
                        >
                          {showRegPassword ? <EyeOff className="w-5 h-5" /> : <Eye className="w-5 h-5" />}
                        </button>
                      </div>
                    </div>
                    <div>
                      <label className="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">Company Name</label>
                      <input 
                        type="text" 
                        required 
                        value={regName}
                        onChange={(e) => setRegName(e.target.value)}
                        className="w-full bg-slate-900/80 border border-slate-800 rounded-xl px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all"
                        placeholder="Acme Corp"
                      />
                    </div>
                    <div>
                      <label className="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">Tenant Name</label>
                      <input 
                        type="text" 
                        required 
                        value={regTenant}
                        onChange={(e) => setRegTenant(e.target.value)}
                        className="w-full bg-slate-900/80 border border-slate-800 rounded-xl px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all"
                        placeholder="acme"
                      />
                    </div>

                    <button 
                      type="submit" 
                      disabled={isLoading}
                      className="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-3 rounded-xl transition-all hover:shadow-lg hover:shadow-indigo-500/20 active:scale-[0.98] disabled:opacity-50 mt-4 flex items-center justify-center gap-2"
                    >
                      <UserPlus className="w-5 h-5" />
                      {isLoading ? 'Creating Account...' : 'Create Corporate Account'}
                    </button>

                    <p className="text-center text-sm text-gray-400 mt-4">
                      Already have an account?{' '}
                      <button type="button" onClick={() => setIsRegistering(false)} className="text-indigo-400 hover:underline">
                        Login Instead
                      </button>
                    </p>
                  </form>
                ) : (
                  <form onSubmit={handleLogin} className="space-y-5">
                    {authStatus === 'IDLE' && (
                      <>
                        <div>
                          <label className="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">Email Address</label>
                          <input 
                            type="email" 
                            required 
                            value={email}
                            onChange={(e) => setEmail(e.target.value)}
                            className="w-full bg-slate-900/80 border border-slate-800 rounded-xl px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all"
                            placeholder="john@example.com"
                          />
                        </div>
                        <div>
                          <label className="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">Password</label>
                          <div className="relative">
                            <input 
                              type={showPassword ? "text" : "password"} 
                              required 
                              value={password}
                              onChange={(e) => setPassword(e.target.value)}
                              className="w-full bg-slate-900/80 border border-slate-800 rounded-xl pl-4 pr-12 py-3 text-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all"
                              placeholder="••••••••"
                            />
                            <button
                              type="button"
                              onClick={() => setShowPassword(!showPassword)}
                              className="absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 hover:text-white transition-colors"
                            >
                              {showPassword ? <EyeOff className="w-5 h-5" /> : <Eye className="w-5 h-5" />}
                            </button>
                          </div>
                        </div>
                      </>
                    )}

                    {authStatus === '2FA_SETUP_REQUIRED' && (
                      <div className="space-y-4">
                        <div className="p-4 bg-indigo-950/40 border border-indigo-500/30 rounded-xl text-center">
                          <ShieldCheck className="w-12 h-12 text-indigo-400 mx-auto mb-2" />
                          <h4 className="font-bold text-white mb-2">Enforce Google Authenticator</h4>
                          <p className="text-xs text-gray-300 mb-4">Scan the secret code inside Google Authenticator to secure administrative privileges.</p>
                          <code className="bg-slate-950 px-3 py-2 rounded font-mono text-sm select-all block text-indigo-300 tracking-widest">{setupSecret}</code>
                        </div>
                        <button 
                          type="button" 
                          onClick={confirm2FASetup}
                          className="w-full bg-indigo-600 hover:bg-indigo-700 py-3 rounded-xl font-semibold transition-all"
                        >
                          I have registered my device
                        </button>
                      </div>
                    )}

                    {authStatus === '2FA_REQUIRED' && (
                      <div>
                        <label className="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">Google Authenticator TOTP Code</label>
                        <div className="relative">
                          <Lock className="w-5 h-5 text-gray-500 absolute left-4 top-3.5" />
                          <input 
                            type="text" 
                            required 
                            maxLength={6}
                            value={totpCode}
                            onChange={(e) => setTotpCode(e.target.value)}
                            className="w-full bg-slate-900/80 border border-slate-800 rounded-xl pl-12 pr-4 py-3 font-mono text-xl tracking-widest text-center text-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all"
                            placeholder="123456"
                          />
                        </div>
                        <p className="text-xs text-gray-400 mt-2 text-center">Enter the 6-digit TOTP key displayed on your device.</p>
                      </div>
                    )}

                    {(authStatus === 'IDLE' || authStatus === '2FA_REQUIRED') && (
                      <div className="space-y-3">
                        <button 
                          type="submit" 
                          disabled={isLoading}
                          className="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-3 rounded-xl transition-all hover:shadow-lg hover:shadow-indigo-500/20 active:scale-[0.98] disabled:opacity-50 flex items-center justify-center gap-2"
                        >
                          {isLoading ? <RefreshCw className="w-5 h-5 animate-spin" /> : <Send className="w-5 h-5" />}
                          {authStatus === '2FA_REQUIRED' ? 'Confirm OTP Token' : 'Authenticate Session'}
                        </button>
                      </div>
                    )}

                    <div className="border-t border-slate-800 pt-4 flex flex-col gap-2 items-center text-sm text-gray-400">
                      <button type="button" onClick={() => {
                        setIsRegistering(true);
                        setRegName('');
                        setRegEmail('');
                        setRegPassword('');
                        setRegTenant('');
                      }} className="text-indigo-400 hover:underline flex items-center gap-1.5 font-medium">
                        <UserPlus className="w-4 h-4" /> Register a Corporate Account
                      </button>
                    </div>
                  </form>
                )}
              </div>
            </div>
          )}
        </div>
      )}
`;

const parts = content.split('{/* 1. AUTH SCREEN PANEL */}');
const endParts = parts[1].split('{/* 2. AUTHENTICATED PANEL SHELL */}');
content = parts[0] + '{/* 1. AUTH SCREEN PANEL */}\\n' + authScreenNewJSX + '\\n\\n      {/* 2. AUTHENTICATED PANEL SHELL */}' + endParts[1];

fs.writeFileSync('frontend/src/App.tsx', content);
console.log('Successfully updated App.tsx');
