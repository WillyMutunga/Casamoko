const fs = require('fs');

// 1. Add API route to api.php
let apiPhp = fs.readFileSync('backend/app/Modules/Messaging/routes/api.php', 'utf8');
if (!apiPhp.includes('getGatewayBalance')) {
  apiPhp = apiPhp.replace(
    "Route::get('/routes', [RouteController::class, 'index']);",
    "Route::get('/routes', [RouteController::class, 'index']);\n    Route::get('/routes/balance', [RouteController::class, 'getGatewayBalance']);"
  );
  fs.writeFileSync('backend/app/Modules/Messaging/routes/api.php', apiPhp);
}

// 2. Add Controller Method
let routeCtrl = fs.readFileSync('backend/app/Modules/Messaging/Controllers/RouteController.php', 'utf8');
if (!routeCtrl.includes('getGatewayBalance')) {
  const method = `
    public function getGatewayBalance()
    {
        try {
            $gateway = app(\\App\\Modules\\Messaging\\Services\\Gateways\\SafaricomSmsGateway::class);
            $balance = $gateway->getBalance();
            return response()->json([
                'success' => true,
                'data' => [
                    'safaricom_balance' => $balance
                ]
            ]);
        } catch (\\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
`;
  routeCtrl = routeCtrl.replace('}', method + '}');
  fs.writeFileSync('backend/app/Modules/Messaging/Controllers/RouteController.php', routeCtrl);
}

// 3. Update App.tsx Frontend
let appTsx = fs.readFileSync('frontend/src/App.tsx', 'utf8').replace(/\r\n/g, '\n');

// Add State for gateway balance
if (!appTsx.includes('gatewayBalance')) {
  appTsx = appTsx.replace(
    "const [lcrRoutes] = useState([",
    "const [gatewayBalance, setGatewayBalance] = useState<string | null>(null);\n  const [isFetchingBalance, setIsFetchingBalance] = useState(false);\n  const [lcrRoutes] = useState(["
  );
}

// Add fetch function inside the component
if (!appTsx.includes('fetchGatewayBalance')) {
  const fetchFunc = `
  const fetchGatewayBalance = async () => {
    setIsFetchingBalance(true);
    try {
      const res = await axios.get('/api/messaging/routes/balance');
      if (res.data.success && res.data.data.safaricom_balance) {
        setGatewayBalance(res.data.data.safaricom_balance);
      } else {
        setGatewayBalance('0.00');
      }
    } catch (e) {
      setGatewayBalance('AUTH_ERR');
    } finally {
      setIsFetchingBalance(false);
    }
  };

  useEffect(() => {
    if (currentPage === 'admin_dashboard') {
      fetchGatewayBalance();
    }
  }, [currentPage]);
`;
  appTsx = appTsx.replace("const [adjustingClient, setAdjustingClient] = useState<any>(null);", "const [adjustingClient, setAdjustingClient] = useState<any>(null);\n" + fetchFunc);
}

// Update UI in Carrier Line Binding Health
const uiTarget = `                              <span className="text-xs font-bold px-2 py-0.5 rounded bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">CONNECTED</span>`;
const uiReplacement = `                              <div className="flex items-center gap-3">
                                {gatewayBalance && (
                                  <span className="text-[10px] font-mono font-bold px-2 py-0.5 rounded bg-indigo-500/10 text-indigo-400 border border-indigo-500/20 flex items-center gap-1">
                                    <Wallet className="w-3 h-3" /> Ksh {gatewayBalance}
                                  </span>
                                )}
                                <span className="text-xs font-bold px-2 py-0.5 rounded bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">CONNECTED</span>
                              </div>`;
appTsx = appTsx.replace(uiTarget, uiReplacement);

fs.writeFileSync('frontend/src/App.tsx', appTsx);
console.log('Balance feature injected successfully.');
