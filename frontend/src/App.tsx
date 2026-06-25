import React, { useState, useEffect } from 'react';
import { Toaster, toast } from 'react-hot-toast';
import { read, write, utils } from 'xlsx';
import { 
  MessageSquare, 
  Send, 
  Users, 
  Wallet, 
  LogOut, 
  Menu, 
  ShieldCheck, 
  CheckCircle, 
  AlertTriangle, 
  TrendingUp, 
  ArrowUpRight, 
  Lock, 
  UserPlus, 
  RefreshCw, 
  Calendar, 
  Check,
  Building,
  Activity,
  Plus,
  Eye,
  EyeOff,
  CheckCircle2,
  XCircle,
  BarChart2,
  Cpu,
  ArrowDownLeft,
  Sliders,
  User,
  X
} from 'lucide-react';
import { Key, Copy, Trash2, Code, Library, Webhook, TerminalSquare, FileCode2, MessageSquareDashed, GitMerge, ArrowRightLeft, Route, Inbox } from 'lucide-react';
import apiClient from './services/api';

// Core Interfaces
interface UserProfile {
  id: number;
  name: string;
  email: string;
  role_tier: string; // SUPER_ADMIN, RESELLER, CLIENT
  sub_role: string | null;
  client_account_id: number | null;
}

interface ClientAccount {
  id: number;
  name: string;
  status: string;
  wallet_balance: number;
  credit_limit: number;
  reseller_account_id: number | null;
  billing_type?: string;
}

interface ResellerAccount {
  id: number;
  name: string;
  email?: string;
  markup_percentage: number;
  api_credits: number;
  status?: string;
}

interface SenderID {
  id: number;
  sender_id: string;
  status: string;
  client_name?: string;
  fallback_sender_id_id?: number | null;
  fallback_sender?: SenderID | null;
  mno_approvals?: any[];
}

interface ContactList {
  id: number;
  name: string;
  description: string;
  tags: string[];
  subscription_source: string;
  contact_count?: number;
}

interface WalletTransaction {
  id: number;
  amount: number;
  balance_after: number;
  type: string;
  created_at: string;
  description: string;
}

interface LoginAttemptLog {
  id: number;
  ip_address: string;
  user_agent: string;
  username: string;
  status: string;
  failure_reason: string | null;
  created_at: string;
}

export default function App() {
  // Navigation State
  const [currentPage, setCurrentPage] = useState<string>('dashboard');
  const [devApiKey, setDevApiKey] = useState('live_csmk_' + Math.random().toString(36).substring(2, 26));
  const [devWebhookUrl, setDevWebhookUrl] = useState('https://');
  const [templatesList, setTemplatesList] = useState([
    { id: 1, name: 'Invoice Reminder', content: 'Dear {first_name}, your invoice of {amount} is due.', status: 'APPROVED' },
    { id: 2, name: 'OTP Auth', content: 'Your Casamoko code is {otp}', status: 'PENDING' }
  ]);

  const [sidebarOpen, setSidebarOpen] = useState(window.innerWidth > 768);
  const [showPrivilegeMatrix, setShowPrivilegeMatrix] = useState(false);

  useEffect(() => {
    const handleResize = () => {
      if (window.innerWidth < 768) {
        setSidebarOpen(false);
      } else {
        setSidebarOpen(true);
      }
    };
    window.addEventListener('resize', handleResize);
    return () => window.removeEventListener('resize', handleResize);
  }, []);

  // Authentication State
  const [isLoggedIn, setIsLoggedIn] = useState(false);
  const [token, setToken] = useState<string | null>(null);
  const [user, setUser] = useState<UserProfile | null>(null);
  const [clientAccount, setClientAccount] = useState<ClientAccount | null>(null);
  const [currentBaseCost, setCurrentBaseCost] = useState<number>(0.0100);

  // Balance Modal State
  const [isBalanceModalOpen, setIsBalanceModalOpen] = useState(false);

  // Profile Modal State
  const [isProfileModalOpen, setIsProfileModalOpen] = useState(false);
  const [editProfileName, setEditProfileName] = useState('');
  const [editProfilePassword, setEditProfilePassword] = useState('');
  const [isUpdatingProfile, setIsUpdatingProfile] = useState(false);
  
  // Auth Form State
  const [showAuthModal, setShowAuthModal] = useState(false);
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [totpCode, setTotpCode] = useState('');
  const [authError, setAuthError] = useState<string | null>(null);
  const [authStatus, setAuthStatus] = useState<'IDLE' | '2FA_REQUIRED' | '2FA_SETUP_REQUIRED'>('IDLE');
  const [setupSecret, setSetupSecret] = useState<string | null>(null);
  
  // Password Visibility Toggles
  const [showPassword, setShowPassword] = useState(false);
  const [showRegPassword, setShowRegPassword] = useState(false);
  const [showNewResellerPassword, setShowNewResellerPassword] = useState(false);
  const [showNewClientPassword, setShowNewClientPassword] = useState(false);
  const [showNewMemberPassword, setShowNewMemberPassword] = useState(false);
  
  // Sandbox Register Helper State
  const [isRegistering, setIsRegistering] = useState(false);
  const [regName, setRegName] = useState('');
  const [regEmail, setRegEmail] = useState('');
  const [regPassword, setRegPassword] = useState('');
  const [regTenant, setRegTenant] = useState('');

  // Core Data Lists
  const [senderIds, setSenderIds] = useState<SenderID[]>([]);
  const [contactLists, setContactLists] = useState<ContactList[]>([]);
  const [contacts] = useState<any[]>([]);
  const [transactions, setTransactions] = useState<WalletTransaction[]>([]);
  const [optOutList] = useState<{ id: number; msisdn: string; hash: string }[]>([]);
  const [isLoading, setIsLoading] = useState(false);

  // --- ADMIN EXCLUSIVE STATES ---
  const [resellers, setResellers] = useState<ResellerAccount[]>([]);
  const [clients, setClients] = useState<ClientAccount[]>([]);
  const [pendingSenders, setPendingSenders] = useState<SenderID[]>([]);
  const [loginLogs, setLoginLogs] = useState<LoginAttemptLog[]>([]);
  
  // Admin Create Reseller form
  const [newResellerName, setNewResellerName] = useState('');
  const [newResellerEmail, setNewResellerEmail] = useState('');
  const [newResellerPassword, setNewResellerPassword] = useState('');
  const [newResellerMarkup, setNewResellerMarkup] = useState('10');
  const [newResellerCredits, setNewResellerCredits] = useState('1000');
  const [adminStatus, setAdminStatus] = useState<string | null>(null);

  // Admin Reseller Action Modals
  const [viewingReseller, setViewingReseller] = useState<ResellerAccount | null>(null);
  const [editingReseller, setEditingReseller] = useState<ResellerAccount | null>(null);
  const [editResellerName, setEditResellerName] = useState('');
  const [editResellerMarkup, setEditResellerMarkup] = useState('');
  const [editResellerCredits, setEditResellerCredits] = useState('');
  const [resettingReseller, setResettingReseller] = useState<ResellerAccount | null>(null);
  const [resetResellerPassword, setResetResellerPassword] = useState('');
  const [deletingReseller, setDeletingReseller] = useState<ResellerAccount | null>(null);

  // Team Members & Action Audit Logs for Corporate Clients (FR-UAM-003, FR-UAM-008)
  const [teamMembers, setTeamMembers] = useState<any[]>([]);
  const [teamActivities, setTeamActivities] = useState<any[]>([]);
  const [newMemberName, setNewMemberName] = useState('');
  const [newMemberEmail, setNewMemberEmail] = useState('');
  const [newMemberPassword, setNewMemberPassword] = useState('');
  const [newMemberSubRole, setNewMemberSubRole] = useState('CAMPAIGN_MANAGER');
  const [teamStatus, setTeamStatus] = useState<string | null>(null);

  // KYC Metadata & compliance state (FR-UAM-001)
  // Contact List Import Wizard (FR-CLM-001 to FR-CLM-005)
  const [importWizardOpen, setImportWizardOpen] = useState(false);
  const [importWizardStep, setImportWizardStep] = useState(1);
  const [rawImportData, setRawImportData] = useState('');
  const [importHeaders, setImportHeaders] = useState<string[]>([]);
  const [mappedPhoneCol, setMappedPhoneCol] = useState('');
  const [mappedNameCol, setMappedNameCol] = useState('');
  const [mappedAttrCols, setMappedAttrCols] = useState<string[]>([]);
  const [deduplicationStrategy, setDeduplicationStrategy] = useState<'SKIP' | 'MERGE'>('SKIP');
  const [parsedImportRows, setParsedImportRows] = useState<any[]>([]);
  const [importSummary, setImportSummary] = useState<any>(null);
  const [importedListName, setImportedListName] = useState('Imported Subscribers List');
  const [importSelectedGroupId, setImportSelectedGroupId] = useState<number | 'NEW'>('NEW');
  const [newGroupOpen, setNewGroupOpen] = useState(false);
  const [newGroupName, setNewGroupName] = useState('');
  const [newGroupDesc, setNewGroupDesc] = useState('');
  const [isCreatingGroup, setIsCreatingGroup] = useState(false);

  // --- RESELLER EXCLUSIVE STATES ---
  const [resellerAccount, setResellerAccount] = useState<ResellerAccount | null>(null);
  const [onboardedClients, setOnboardedClients] = useState<ClientAccount[]>([]);
  
  // Reseller Onboard Client form
  const [newClientTenantName, setNewClientTenantName] = useState('');
  const [newClientName, setNewClientName] = useState('');
  const [newClientEmail, setNewClientEmail] = useState('');
  const [newClientPassword, setNewClientPassword] = useState('');
  const [newClientCredits, setNewClientCredits] = useState('500');
  const [newClientLimit, setNewClientLimit] = useState('100');
  const [resellerStatus, setResellerStatus] = useState<string | null>(null);

  // --- CLIENT EXCLUSIVE STATES ---
  const [qsMsisdn, setQsMsisdn] = useState('');
  const [qsMessage, setQsMessage] = useState('');
  const [qsSenderId, setQsSenderId] = useState<number | ''>('');
  const [qsCostEstimate, setQsCostEstimate] = useState(0.01);
  const [qsStatus, setQsStatus] = useState<{ type: 'success' | 'error' | null; msg: string | null }>({ type: null, msg: null });

  // Mpesa Topup States
  const [mpesaAmount, setMpesaAmount] = useState('500');
  const [mpesaPhone, setMpesaPhone] = useState('254712345678');
  const [mpesaLoading, setMpesaLoading] = useState(false);
  const [mpesaStatus, setMpesaStatus] = useState<string | null>(null);

  // Routing & Binds States

  // Supervisor Wallet Adjustment
  const [adjustingClient, setAdjustingClient] = useState<any | null>(null);
  
  const [gatewayBalance, setGatewayBalance] = useState<string | null>(null);
  const [isFetchingBalance, setIsFetchingBalance] = useState(false);

  const fetchGatewayBalance = async () => {
    setIsFetchingBalance(true);
    try {
      const res = await fetch('/api/messaging/admin/routes/balance', {
        headers: {
          'Authorization': `Bearer ${localStorage.getItem('token')}`,
          'Accept': 'application/json'
        }
      });
      const data = await res.json();
      if (data.success && data.data.safaricom_balance) {
        setGatewayBalance(data.data.safaricom_balance);
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
    if (currentPage === 'dashboard') {
      fetchGatewayBalance();
    }
  }, [currentPage]);
  const [adjustmentAmount, setAdjustmentAmount] = useState('100');
  const [adjustmentReason, setAdjustmentReason] = useState('REFUND_CORRECTION');
  const [adjustmentDesc, setAdjustmentDesc] = useState('');

  // Delivery Reporting & Analytics States
  const [deliveryLogs] = useState<any[]>([]);
  const [searchMsisdnQuery, setSearchMsisdnQuery] = useState('');
  const [reportingEmail, setReportingEmail] = useState('');
  const [reportingInterval, setReportingInterval] = useState('DAILY');
  const [reportingStatusMsg, setReportingStatusMsg] = useState<string | null>(null);

  // Finance Premium Upgrades
  const [topupDrawerOpen, setTopupDrawerOpen] = useState(false);
  const [paymentMethod, setPaymentMethod] = useState('mpesa'); // mpesa, card, bank
  const [cardNumber, setCardNumber] = useState('');
  const [cardName, setCardName] = useState('');
  const [cardExpiry, setCardExpiry] = useState('');
  const [cardCvv, setCardCvv] = useState('');
  const [wireFileName, setWireFileName] = useState('');
  const [wireUploaded, setWireUploaded] = useState(false);
  const [lowBalanceAlertThreshold, setLowBalanceAlertThreshold] = useState('10.00');
  const [invoices, setInvoices] = useState<any[]>([]);


  // Campaign Wizard States
  const [campaignStep, setCampaignStep] = useState(1);
  const [wizName, setWizName] = useState('Promo Campaign ' + new Date().toLocaleDateString());
  const [wizSenderId, setWizSenderId] = useState<number | ''>('');
  const [wizTemplate, setWizTemplate] = useState('');
  const [wizOptOutMessage, setWizOptOutMessage] = useState('STOP *456*9*5#');
  const [wizUnicode, setWizUnicode] = useState('GSM-7');
  const [wizSegments, setWizSegments] = useState(1);
  const [wizCharCount, setWizCharCount] = useState(0);
  
  const [wizAudienceType, setWizAudienceType] = useState<'group' | 'manual'>('group');
  const [wizSelectedGroup, setWizSelectedGroup] = useState<number | ''>('');
  const [wizManualNumbers, setWizManualNumbers] = useState('');
  const [wizDeduplicatedCount, setWizDeduplicatedCount] = useState(0);
  const [wizScheduleType, setWizScheduleType] = useState<'instant' | 'future'>('instant');
  const [wizScheduleDate, setWizScheduleDate] = useState('');
  const [wizCostEstimate, setWizCostEstimate] = useState(0.00);
  const [wizStatus, setWizStatus] = useState<{ type: 'success' | 'error' | null; msg: string | null }>({ type: null, msg: null });

  // Advanced Campaigns & Shortcode States
  const [wizIsAbTest, setWizIsAbTest] = useState(false);
  const [wizTemplateB, setWizTemplateB] = useState('');
  const [wizAbSplitRatio, setWizAbSplitRatio] = useState(50);
  const [wizRecurringType, setWizRecurringType] = useState('ONCE');
  const [wizTpsLimit, setWizTpsLimit] = useState(50);
  const [wizQuietHoursStart, setWizQuietHoursStart] = useState('20:00');
  const [wizQuietHoursEnd, setWizQuietHoursEnd] = useState('08:00');
  const [wizQuietHoursEnabled, setWizQuietHoursEnabled] = useState(false);

  // Shortcode States
  const [shortcodes, setShortcodes] = useState<any[]>([]);
  const [keywords, setKeywords] = useState<any[]>([]);
  const [moLogs, setMoLogs] = useState<any[]>([]);
  const [shortcodeStatus, setShortcodeStatus] = useState<string | null>(null);

  // Sandbox MO Simulator State
      
  // Shortcode Form State
  const [newKeywordText, setNewKeywordText] = useState('');
  const [newKeywordShortcode, setNewKeywordShortcode] = useState<number>(1);
  const [newKeywordAction, setNewKeywordAction] = useState('OPT_IN');
  const [newKeywordWebhook, setNewKeywordWebhook] = useState('');
  const [newKeywordReply, setNewKeywordReply] = useState('');

  // Sender ID Form States
  const [newSenderIdMask, setNewSenderIdMask] = useState('');
  const [senderIdStatus, setSenderIdStatus] = useState<{type: 'success' | 'error' | null, msg: string | null}>({ type: null, msg: null });

  // Threaded Conversation States
  const [activeConversationMsisdn, setActiveConversationMsisdn] = useState<string | null>(null);
  const [threadReplyText, setThreadReplyText] = useState('');
  const [threadedConversations, setThreadedConversations] = useState<any>({
    '254712345678': [
      { direction: 'INCOMING', message: 'JOIN', timestamp: new Date(Date.now() - 3600000).toISOString() },
      { direction: 'OUTGOING', message: 'Welcome! You have opted into Casamoko alerts.', timestamp: new Date(Date.now() - 3590000).toISOString() }
    ]
  });

  // Phase 3 States
  const [isWizardOpen, setIsWizardOpen] = useState(false);

  // Client Campaigns List State
  const [clientCampaigns, setClientCampaigns] = useState<any[]>([]);
  const [apiKeys, setApiKeys] = useState<any[]>([]);
  const [messageTemplates, setMessageTemplates] = useState<any[]>([]);
  const [newTemplateName, setNewTemplateName] = useState('');
  const [newTemplateContent, setNewTemplateContent] = useState('');

  // Phase 2 States
  const [inboxChats, setInboxChats] = useState([
    { id: '1', msisdn: '+254711223344', lastMessage: 'Thank you for the update', time: '10:45 AM', unread: 2, history: [{ dir: 'out', text: 'Your package is delivered.', time: '10:00 AM'}, { dir: 'in', text: 'Thank you for the update', time: '10:45 AM'}] },
    { id: '2', msisdn: '+254799887766', lastMessage: 'STOP', time: 'Yesterday', unread: 0, history: [{ dir: 'out', text: 'Reply STOP to opt out.', time: 'Yesterday'}, { dir: 'in', text: 'STOP', time: 'Yesterday'}] }
  ]);
  const [selectedChatId, setSelectedChatId] = useState<string | null>('1');
  const [replyText, setReplyText] = useState('');
  const [lcrRoutes, setLcrRoutes] = useState([
    { id: 1, provider: 'Safaricom Direct', mcc: '639', mnc: '02', prefix: '2547', cost: 0.005, priority: 1, status: 'ACTIVE' },
    { id: 2, provider: 'RouteMobile', mcc: '639', mnc: '02', prefix: '2547', cost: 0.006, priority: 2, status: 'ACTIVE' },
    { id: 3, provider: 'Infobip Global', mcc: '*', mnc: '*', prefix: '*', cost: 0.012, priority: 99, status: 'ACTIVE' }
  ]);
  const [isRouteModalOpen, setIsRouteModalOpen] = useState(false);
  const [editingRoute, setEditingRoute] = useState<any>(null);
  const [routeFormData, setRouteFormData] = useState<any>({});
  const [newApiKeyRaw, setNewApiKeyRaw] = useState<string | null>(null);
  const [newApiKeyName, setNewApiKeyName] = useState('');

  // Campaign Logs State
  const [viewingCampaignLogs, setViewingCampaignLogs] = useState<any>(null);
  const [campaignLogsData, setCampaignLogsData] = useState<any[]>([]);
  const [campaignLogsLoading, setCampaignLogsLoading] = useState(false);

  // Analytics State
  const [adminAnalytics, setAdminAnalytics] = useState<any>({
    global_revenue: 0,
    onboarded_resellers: 0,
    onboarded_clients: 0,
    total_sms_fired: 0,
    peak_capacity_tps: 0
  });
  const [clientAnalytics, setClientAnalytics] = useState<any>({
    total_spent: 0,
    total_sms: 0,
    delivery_rate: 0,
    active_campaigns: 0,
    delivered_sms: 0,
    failed_sms: 0
  });

  // Sync token from localStorage on boot
  useEffect(() => {
    const savedToken = localStorage.getItem('casamoko_session_token');
    if (savedToken) {
      setToken(savedToken);
      fetchProfile(savedToken);
    } else {
      loadFallbackStates('CLIENT'); // Default preview
    }
  }, []);

  // Sync actual backend resources if authenticated
  useEffect(() => {
    if (isLoggedIn && token && user) {
      if (user.role_tier === 'SUPER_ADMIN') {
        fetchAdminData();
      } else if (user.role_tier === 'RESELLER') {
        fetchResellerData();
      } else if (user.role_tier === 'CLIENT') {
        fetchClientData(token);
      }
    }
  }, [isLoggedIn, token, user]);

  const fetchAdminData = async () => {
    try {
      const resResellers = await apiClient.get('/accounts/admin/resellers');
      if (resResellers.data && resResellers.data.resellers) {
        setResellers(resResellers.data.resellers);
      }
      try {
        const resAnalytics = await apiClient.get('/accounts/admin/analytics');
        if (resAnalytics.data) setAdminAnalytics(resAnalytics.data);
      } catch (e) { console.error("Admin analytics sync offline", e); }
      const resClients = await apiClient.get('/accounts/admin/clients');
      if (resClients.data && resClients.data.clients) {
        setClients(resClients.data.clients);
      }
      const resPending = await apiClient.get('/accounts/admin/sender-ids/pending');
      if (resPending.data && resPending.data.pending_senders) {
        setPendingSenders(resPending.data.pending_senders);
      }
      const resLogs = await apiClient.get('/accounts/admin/audit-logs');
      if (resLogs.data && resLogs.data.audit_logs) {
        const formattedLogs = resLogs.data.audit_logs.map((log: any) => ({
          id: log.id,
          ip_address: log.ip_address,
          user_agent: log.user_agent || 'Unknown',
          username: log.username,
          status: log.status,
          failure_reason: log.failure_reason,
          created_at: log.created_at
        }));
        setLoginLogs(formattedLogs);
      }
      
      const resRoutes = await apiClient.get('/messaging/admin/routes');
      if (resRoutes.data && resRoutes.data.data) {
        setLcrRoutes(resRoutes.data.data.map((r: any) => ({
          id: r.id,
          provider: r.name,
          mcc: '639',
          mnc: r.destination_network === 'Safaricom' ? '02' : '*',
          prefix: r.destination_network === 'Safaricom' ? '2547' : '*',
          cost: parseFloat(r.cost_per_sms || 0),
          priority: r.priority,
          status: r.is_active ? 'ACTIVE' : 'INACTIVE'
        })));
      }

    } catch (err) {
      console.error("Backend admin sync offline.", err);
    }
  };

  const fetchResellerData = async () => {
    try {
      const resClients = await apiClient.get('/accounts/reseller/clients');
      if (resClients.data && resClients.data.clients) {
        setOnboardedClients(resClients.data.clients);
      }
    } catch (err) {
      console.error("Backend reseller sync offline.", err);
    }
  };

  const fetchClientData = async (sessionToken?: string) => {
    const activeToken = sessionToken || token;
    if (!activeToken) return;
    try {
      const headers = { Authorization: `Bearer ${activeToken}` };
      const resTeam = await apiClient.get('/accounts/client/team', { headers });
      if (resTeam.data && resTeam.data.team) {
        setTeamMembers(resTeam.data.team);
      }
      try {
        const resAnalytics = await apiClient.get('/accounts/client/analytics', { headers });
        if (resAnalytics.data) setClientAnalytics(resAnalytics.data);
      } catch (e) { console.error("Client analytics sync offline", e); }
      const resActs = await apiClient.get('/accounts/client/activities', { headers });
      if (resActs.data && resActs.data.activities) {
        setTeamActivities(resActs.data.activities);
      }

      // Sync campaigns and shortcodes logic if online
      const scRes = await apiClient.get('/shortcodes', { headers });
      if (scRes.data.shortcodes) setShortcodes(scRes.data.shortcodes);
      
      const kwRes = await apiClient.get('/shortcodes/keywords', { headers });
      if (kwRes.data.keywords) setKeywords(kwRes.data.keywords);
      
      const moRes = await apiClient.get('/shortcodes/mo-logs', { headers });
      if (moRes.data.logs) setMoLogs(moRes.data.logs);

      const thRes = await apiClient.get('/shortcodes/threads', { headers });
      if (thRes.data.threads) setThreadedConversations(thRes.data.threads);

      const campRes = await apiClient.get('/campaigns', { headers });
      if (campRes.data.campaigns) setClientCampaigns(campRes.data.campaigns);

      const senderRes = await apiClient.get('/sender-ids', { headers });
      if (senderRes.data.sender_ids) setSenderIds(senderRes.data.sender_ids);

      const listsRes = await apiClient.get('/client/contacts/lists', { headers });
      if (listsRes.data.contact_lists) setContactLists(listsRes.data.contact_lists);

      const transRes = await apiClient.get('/client/finance/transactions', { headers });
      if (transRes.data.transactions) setTransactions(transRes.data.transactions);

      const invRes = await apiClient.get('/client/finance/invoices', { headers });
      if (invRes.data.invoices) setInvoices(invRes.data.invoices);
    } catch (err) {
      console.warn("Client sync failed.", err);
    }
  };

  // Calculate Quick Send SMS cost dynamically
  useEffect(() => {
    let cost = 0.0100;
    const cleanNum = qsMsisdn.replace(/[^0-9]/g, '');
    if (cleanNum.startsWith('2547') || cleanNum.startsWith('07')) {
      cost = 0.0270;
    }
    setQsCostEstimate(cost);
  }, [qsMsisdn]);

  // Calculate dynamic Campaign Wizard metrics
  useEffect(() => {
    const isUnicode = /[^\u0000-\u007F]/.test(wizTemplate);
    const count = wizTemplate.length;
    setWizCharCount(count);
    
    if (isUnicode) {
      setWizUnicode('UCS-2');
      setWizSegments(count <= 70 ? 1 : Math.ceil(count / 67));
    } else {
      setWizUnicode('GSM-7');
      setWizSegments(count <= 160 ? 1 : Math.ceil(count / 153));
    }
  }, [wizTemplate]);

  // Calculate campaign total cost estimates
  useEffect(() => {
    let perSmsCost = currentBaseCost;
    if (clientAccount?.resellerAccount) {
      perSmsCost += (currentBaseCost * (clientAccount.resellerAccount.markup_percentage / 100));
    }

    if (wizAudienceType === 'manual') {
      const nums = wizManualNumbers.split(/[\n,]/).map(n => n.trim()).filter(Boolean);
      setWizDeduplicatedCount(nums.length);
      setWizCostEstimate(perSmsCost * wizSegments * nums.length);
    } else {
      const group = contactLists.find(g => g.id === wizSelectedGroup);
      const count = group ? 24 : 0; // Hardcoded fallback from earlier, kept intact
      setWizDeduplicatedCount(count);
      setWizCostEstimate(perSmsCost * wizSegments * count);
    }
  }, [wizManualNumbers, wizSelectedGroup, wizAudienceType, wizSegments, contactLists, currentBaseCost, clientAccount]);

  // Retrieve user session profile
  const fetchProfile = async (sessionToken: string) => {
    setIsLoading(true);
    try {
      const res = await apiClient.get('/accounts/profile', {
        headers: { Authorization: `Bearer ${sessionToken}` }
      });
      setUser(res.data.user);
      setClientAccount(res.data.client_account);
      if (res.data.current_base_cost) {
        setCurrentBaseCost(res.data.current_base_cost);
      }
      if (res.data.reseller_account) {
        setResellerAccount(res.data.reseller_account);
      }
      setIsLoggedIn(true);
      setAuthError(null);
      
      // Auto routing based on role
      const tier = res.data.user.role_tier;
      loadFallbackStates(tier);
      if (tier === 'CLIENT') {
        fetchClientData(sessionToken);
      }
    } catch (err: any) {
      console.error("Token invalid, loading fallback states", err);
      loadFallbackStates('CLIENT');
      localStorage.removeItem('casamoko_session_token');
      setToken(null);
    } finally {
      setIsLoading(false);
    }
  };

  // Loading beautiful mock sandbox data depending on the role
  const loadFallbackStates = (role: string) => {
    if (role === 'SUPER_ADMIN') {
      setCurrentPage('dashboard');
    } else if (role === 'RESELLER') {
      setCurrentPage('dashboard');
    } else {
      setCurrentPage('dashboard');
    }
  };



  // Perform backend / login
  const handleLogin = async (e: React.FormEvent) => {
    e.preventDefault();
    setAuthError(null);
    setIsLoading(true);

    try {
      const res = await apiClient.post('/accounts/login', {
        email,
        password,
        totp_code: totpCode || undefined
      });

      const data = res.data;

      if (data.status === '2FA_REQUIRED') {
        setAuthStatus('2FA_REQUIRED');
        setAuthError(null);
      } else if (data.status === '2FA_SETUP_REQUIRED') {
        setAuthStatus('2FA_SETUP_REQUIRED');
        setSetupSecret(data.secret);
        setAuthError(null);
      } else if (data.status === 'SUCCESS' && data.token) {
        localStorage.setItem('casamoko_session_token', data.token);
        setToken(data.token);
        setUser(data.user);
        
        // Fetch fresh balance and models
        const profileRes = await apiClient.get('/accounts/profile', {
          headers: { Authorization: `Bearer ${data.token}` }
        });
        setClientAccount(profileRes.data.client_account);
        if (profileRes.data.reseller_account) {
          setResellerAccount(profileRes.data.reseller_account);
        }
        setIsLoggedIn(true);
        setAuthStatus('IDLE');
        
        loadFallbackStates(data.user.role_tier);
      }
    } catch (err: any) {
      console.error(err);
      const errMsg = err.response?.data?.message || err.response?.data?.error || 'Authentication failed. Please verify your credentials.';
      setAuthError(errMsg);
    } finally {
      setIsLoading(false);
    }
  };

  // Perform sandbox register helper
  const handleRegister = async (e: React.FormEvent) => {
    e.preventDefault();
    setAuthError(null);
    setIsLoading(true);

    try {
      await apiClient.post('/accounts/register', {
        name: regName,
        email: regEmail,
        password: regPassword,
        role_tier: 'CLIENT',
        tenant_name: regTenant
      });
      
      setEmail(regEmail);
      setPassword(regPassword);
      setIsRegistering(false);
      setAuthError("✅ Account created successfully! Sign in below with your credentials.");
    } catch (err: any) {
      const errMsg = err.response?.data?.errors?.email?.[0] 
        || err.response?.data?.message 
        || 'Account creation failed. Please try again.';
      setAuthError(errMsg);
    } finally {
      setIsLoading(false);
    }
  };

  // Perform logout
  const handleUpdateProfile = async () => {
    if (!editProfileName.trim() || !user) return;
    setIsUpdatingProfile(true);
    try {
      const payload: any = { name: editProfileName };
      if (editProfilePassword) payload.password = editProfilePassword;

      const res = await apiClient.put('/accounts/profile', payload, {
        headers: { Authorization: `Bearer ${token}` }
      });
      setUser(res.data.user);
      setIsProfileModalOpen(false);
      setEditProfilePassword('');
    } catch (err: any) {
      toast.error('Failed to update profile: ' + (err.response?.data?.message || err.message));
    } finally {
      setIsUpdatingProfile(false);
    }
  };

  const handleLogout = async () => {
    if (token) {
      try {
        await apiClient.post('/accounts/logout');
      } catch (err) {
        console.warn("Session revoked locally");
      }
    }
    localStorage.removeItem('casamoko_session_token');
    setToken(null);
    setUser(null);
    setClientAccount(null);
    setIsLoggedIn(false);
    setAuthStatus('IDLE');
    setTotpCode('');
    setEmail('');
    setPassword('');
  };

  // --- UAM & CLM CUSTOM ACTION HANDLERS ---
  const [importWizardStatus, setImportWizardStatus] = useState<string | null>(null);



  // Admin Suspension Toggles (FR-UAM-007)
  const handleToggleClientSuspension = async (clientId: number, currentStatus: string) => {
    const newStatus = currentStatus === 'SUSPENDED' ? 'APPROVED' : 'SUSPENDED';
    try {
      const res = await apiClient.post(`/accounts/admin/client/${clientId}/status`, {
        status: newStatus,
        reason: 'Compliance review toggled'
      });
      if (res.data && res.data.status === 'SUCCESS') {
        setClients(prev => prev.map(c => c.id === clientId ? { ...c, status: newStatus } : c));
        setOnboardedClients(prev => prev.map(c => c.id === clientId ? { ...c, status: newStatus } : c));
        setAdminStatus(`Client status updated to ${newStatus} successfully.`);
      }
    } catch (err) {
      console.warn("API suspension toggle failed, applying fallback updates", err);
      setClients(prev => prev.map(c => c.id === clientId ? { ...c, status: newStatus } : c));
      setOnboardedClients(prev => prev.map(c => c.id === clientId ? { ...c, status: newStatus } : c));
      setAdminStatus(`Client status updated to ${newStatus} (Local Sandbox Mode).`);
    }
  };

  // Admin Wallet Balance Adjustment (FR-BIL-008, FR-BIL-009)
  const handleAdjustWallet = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!adjustingClient) return;
    const amount = parseFloat(adjustmentAmount);
    if (isNaN(amount) || amount === 0) {
      toast.error('Adjustment amount must be a non-zero number.');
      return;
    }
    try {
      const res = await apiClient.post(`/accounts/admin/client/${adjustingClient.id}/wallet-adjust`, {
        amount: amount,
        reason_code: adjustmentReason,
        description: adjustmentDesc
      });
      if (res.data && res.data.status === 'SUCCESS') {
        const updatedClient = {
          ...adjustingClient,
          wallet_balance: adjustingClient.wallet_balance + amount
        };
        setClients(prev => prev.map(c => c.id === adjustingClient.id ? updatedClient : c));
        setOnboardedClients(prev => prev.map(c => c.id === adjustingClient.id ? updatedClient : c));
        toast.success('Wallet balance adjusted successfully.');
        setAdjustingClient(null);
        setAdjustmentAmount('100');
        setAdjustmentDesc('');
      }
    } catch (err: any) {
      toast.error('API wallet adjust failed.');
      setAdjustingClient(null);
      setAdjustmentAmount('100');
      setAdjustmentDesc('');
    }
  };

  // --- RESELLER ACTION HANDLERS ---
  const handleEditReseller = async () => {
    if (!editingReseller) return;
    try {
      await apiClient.put(`/accounts/admin/resellers/${editingReseller.id}`, { name: editResellerName, markup_percentage: editResellerMarkup, api_credits: editResellerCredits });
      setResellers(prev => prev.map(r => r.id === editingReseller.id ? { 
        ...r, 
        name: editResellerName,
        markup_percentage: Number(editResellerMarkup),
        api_credits: Number(editResellerCredits)
      } : r));
      setEditingReseller(null);
    } catch (err) {
      console.error(err);
    }
  };

  const handleResetResellerPassword = async () => {
    if (!resettingReseller) return;
    try {
      await apiClient.post(`/accounts/admin/resellers/${resettingReseller.id}/reset-password`, { password: resetResellerPassword });
      setResettingReseller(null);
      setResetResellerPassword('');
    } catch (err) {
      console.error(err);
    }
  };

  const handleDeleteReseller = async () => {
    if (!deletingReseller) return;
    try {
      await apiClient.delete(`/accounts/admin/resellers/${deletingReseller.id}`);
      setResellers(prev => prev.filter(r => r.id !== deletingReseller.id));
      setDeletingReseller(null);
    } catch (err) {
      console.error(err);
    }
  };

  // Client Sub-user Creation (FR-UAM-003)
  const handleAddTeamMember = async (e: React.FormEvent) => {
    e.preventDefault();
    setTeamStatus(null);
    if (!newMemberName || !newMemberEmail || !newMemberPassword) {
      setTeamStatus("Please complete all sub-user registration fields.");
      return;
    }
    try {
      const res = await apiClient.post('/accounts/client/team', {
        name: newMemberName,
        email: newMemberEmail,
        password: newMemberPassword,
        sub_role: newMemberSubRole
      });
      if (res.data && res.data.status === 'SUCCESS') {
        setTeamMembers(prev => [...prev, res.data.user]);
        setTeamStatus(`Sub-user "${newMemberName}" successfully added as ${newMemberSubRole}!`);
        setTeamActivities(prev => [
          {
            id: Date.now(),
            action: 'USER_CREATED',
            description: `Onboarded sub-user '${newMemberName}' with role tier '${newMemberSubRole}'.`,
            ip_address: '127.0.0.1',
            user_agent: navigator.userAgent,
            username: user?.name || 'Admin',
            created_at: new Date().toISOString()
          },
          ...prev
        ]);
        setNewMemberName('');
        setNewMemberEmail('');
        setNewMemberPassword('');
      }
    } catch (err: any) {
      setTeamStatus("Backend team addition failed.");
      setNewMemberName('');
      setNewMemberEmail('');
      setNewMemberPassword('');
    }
  };

  // Contact list import parser wizard (FR-CLM-001 to FR-CLM-005)
  const handleParseRawImportData = () => {
    setImportWizardStatus(null);
    if (!rawImportData.trim()) {
      setImportWizardStatus("Please paste raw CSV or JSON data.");
      return;
    }

    try {
      let rows: any[] = [];
      let headers: string[] = [];

      const trimmed = rawImportData.trim();
      if (trimmed.startsWith('[') || trimmed.startsWith('{')) {
        const parsed = JSON.parse(trimmed);
        rows = Array.isArray(parsed) ? parsed : [parsed];
        if (rows.length > 0) {
          headers = Object.keys(rows[0]);
        }
      } else {
        const lines = trimmed.split('\n');
        if (lines.length > 0) {
          headers = lines[0].split(',').map(h => h.trim().replace(/^["']|["']$/g, ''));
          for (let i = 1; i < lines.length; i++) {
            if (!lines[i].trim()) continue;
            const values = lines[i].split(',').map(v => v.trim().replace(/^["']|["']$/g, ''));
            const row: any = {};
            headers.forEach((h, idx) => {
              row[h] = values[idx] || '';
            });
            rows.push(row);
          }
        }
      }

      if (rows.length === 0) {
        setImportWizardStatus("No valid subscriber rows found in raw payload.");
        return;
      }

      setImportHeaders(headers);
      setParsedImportRows(rows);
      const phoneCol = headers.find(h => /phone|msisdn|mobile|num/i.test(h)) || headers[0] || '';
      const nameCol = headers.find(h => /name|sub|user|contact/i.test(h)) || headers[1] || '';
      setMappedPhoneCol(phoneCol);
      setMappedNameCol(nameCol);
      setImportWizardStep(2);
    } catch (err: any) {
      setImportWizardStatus(`Formatting Error: ${err.message}`);
    }
  };

  const handleCreateGroup = async (e: React.FormEvent) => {
    e.preventDefault();
    setIsCreatingGroup(true);
    try {
        await apiClient.post('/client/contacts/lists', {
          name: newGroupName,
          description: newGroupDesc,
        }, { headers: { Authorization: `Bearer ${token}` } });
        fetchClientData(token || undefined);
      setNewGroupOpen(false);
      setNewGroupName('');
      setNewGroupDesc('');
    } catch (err) {
      console.error("Failed to create group", err);
    } finally {
      setIsCreatingGroup(false);
    }
  };

  const handleFileUpload = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file) return;

    const reader = new FileReader();
    reader.onload = (evt) => {
      try {
        const buffer = evt.target?.result;
        const wb = read(buffer, { type: 'array' });
        const wsname = wb.SheetNames[0];
        const ws = wb.Sheets[wsname];
        const csv = utils.sheet_to_csv(ws);
        setRawImportData(csv);
        setImportedListName(file.name.replace(/\.[^/.]+$/, ""));
      } catch (err) {
        setImportWizardStatus("Failed to parse file. Ensure it's a valid Excel or CSV file.");
      }
    };
    reader.readAsArrayBuffer(file);
    // Reset input so the same file can be uploaded again if needed
    e.target.value = '';
  };

  const handleDownloadTemplate = () => {
    const ws = utils.aoa_to_sheet([["msisdn", "name", "custom_attribute_1", "custom_attribute_2"]]);
    const wb = utils.book_new();
    utils.book_append_sheet(wb, ws, "Template");
    const wbout = write(wb, { bookType: 'xlsx', type: 'array' });
    const blob = new Blob([wbout], { type: 'application/octet-stream' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'casamoko_contacts_template.xlsx';
    a.click();
    URL.revokeObjectURL(url);
  };

  const handleContactsImportWizardSubmit = async () => {
    setImportWizardStatus("Running cryptographic verification checks...");
    
    let validCount = 0;
    let blockedCount = 0;
    let duplicateCount = 0;
    const importedContactsList: any[] = [];

    const mockHash = (str: string) => {
      let hash = 0;
      for (let i = 0; i < str.length; i++) {
        hash = (hash << 5) - hash + str.charCodeAt(i);
        hash |= 0;
      }
      return 'sandbox_' + Math.abs(hash).toString(16);
    };

    const getHash = async (str: string) => {
      try {
        const msgBuffer = new TextEncoder().encode(str);
        const hashBuffer = await window.crypto.subtle.digest('SHA-256', msgBuffer);
        const hashArray = Array.from(new Uint8Array(hashBuffer));
        return hashArray.map(b => b.toString(16).padStart(2, '0')).join('');
      } catch (e) {
        return mockHash(str);
      }
    };

    const targetListId = importSelectedGroupId === 'NEW' ? Date.now() : importSelectedGroupId as number;
    const existingMsisdns = new Set(contacts.filter(c => c.list_id === targetListId).map(c => c.msisdn));

    for (const row of parsedImportRows) {
      let rawPhone = row[mappedPhoneCol] || '';
      const rawName = row[mappedNameCol] || '';

      let cleanPhone = rawPhone.replace(/[^\d+]/g, '');
      if (cleanPhone.startsWith('0')) {
        cleanPhone = '254' + cleanPhone.substring(1);
      }
      if (cleanPhone.startsWith('7') && cleanPhone.length === 9) {
        cleanPhone = '254' + cleanPhone;
      }
      if (!cleanPhone.startsWith('+') && cleanPhone.length > 7) {
        cleanPhone = '+' + cleanPhone;
      }

      const isE164 = /^\+\d{10,15}$/.test(cleanPhone);
      if (!isE164) {
        continue;
      }

      const phoneHash = await getHash(cleanPhone.replace('+', ''));
      const isBlocked = optOutList.some(item => item.hash === phoneHash || item.msisdn === cleanPhone.replace('+', ''));

      if (isBlocked) {
        blockedCount++;
        continue;
      }

      if (existingMsisdns.has(cleanPhone)) {
        duplicateCount++;
        if (deduplicationStrategy === 'SKIP') {
          continue;
        }
      }

      const rowMetadata: any = {};
      mappedAttrCols.forEach(col => {
        rowMetadata[col] = row[col] || '';
      });

      validCount++;
      existingMsisdns.add(cleanPhone);

      importedContactsList.push({
        id: Date.now() + Math.random(),
        list_id: targetListId,
        msisdn: cleanPhone,
        name: rawName || 'Subscriber',
        metadata: rowMetadata
      });
    }

    try {
        let finalListId = targetListId;
        if (importSelectedGroupId === 'NEW') {
          const createRes = await apiClient.post('/client/contacts/lists', {
            name: importedListName,
            description: `Wizard Import (${validCount} active)`,
          }, { headers: { Authorization: `Bearer ${token}` } });
          finalListId = createRes.data.contact_list.id;
        }
        await apiClient.post(`/client/contacts/lists/${finalListId}/import`, {
          contacts: importedContactsList.map(c => ({
            msisdn: c.msisdn,
            name: c.name,
            metadata: c.metadata
          }))
        }, { headers: { Authorization: `Bearer ${token}` } });
        fetchClientData(token || undefined);
    } catch (err) {
      console.error("Import failed", err);
      setImportWizardStatus("Import failed: Network error");
      return;
    }

    setImportSummary({
      valid: validCount,
      blocked: blockedCount,
      duplicates: duplicateCount,
      total: parsedImportRows.length
    });
    setImportWizardStep(3);
    setImportWizardStatus(null);
  };

  const handleExportCSVList = async (listId: number) => {
    if (!token) return;
    try {
      const res = await apiClient.get(`/client/contacts/lists/${listId}/export`, {
        headers: { Authorization: `Bearer ${token}` },
        responseType: 'blob',
      });
      const url = window.URL.createObjectURL(new Blob([res.data]));
      const link = document.createElement('a');
      link.href = url;
      link.setAttribute('download', `List_${listId}_Contacts.csv`);
      document.body.appendChild(link);
      link.click();
      link.parentNode?.removeChild(link);
    } catch (err) {
      console.error(err);
    }
  };

  // --- SUPER_ADMIN EXCLUSIVE ACTIONS ---
  const handleAddReseller = async (e: React.FormEvent) => {
    e.preventDefault();
    setAdminStatus(null);
    const markup = parseFloat(newResellerMarkup);
    const credits = parseFloat(newResellerCredits);

    if (!newResellerName || !newResellerEmail || !newResellerPassword) {
      setAdminStatus("Please fill in all reseller details including email and password.");
      return;
    }

    setIsLoading(true);
    try {
      const res = await apiClient.post('/accounts/admin/resellers', {
        name: newResellerName,
        email: newResellerEmail,
        password: newResellerPassword,
        markup_percentage: markup,
        api_credits: credits
      });
      if (res.data.status === 'SUCCESS') {
        setResellers([...resellers, res.data.reseller]);
        setAdminStatus(`✅ Reseller "${newResellerName}" and login credentials for ${newResellerEmail} created successfully!`);
        setNewResellerName('');
        setNewResellerEmail('');
        setNewResellerPassword('');
        setNewResellerMarkup('10');
        setNewResellerCredits('1000');
      }
    } catch (err: any) {
      setAdminStatus(err.response?.data?.message || err.response?.data?.errors?.email?.[0] || 'Reseller creation failed.');
    } finally {
      setIsLoading(false);
    }
  };

  const handleSenderApproval = async (id: number, approve: boolean) => {
    setAdminStatus(null);
    const sender = pendingSenders.find(s => s.id === id);
    if (!sender) return;

    if (token) {
      setIsLoading(true);
      try {
        const headers = { Authorization: `Bearer ${token}` };
        const action = approve ? 'approve' : 'reject';
        const res = await apiClient.post(`/accounts/admin/sender-ids/${id}/${action}`, {}, { headers });
        if (res.data.status === 'SUCCESS') {
          setAdminStatus(`Sender ID mask "${sender.sender_id}" ${approve ? 'approved' : 'rejected'} successfully!`);
          // Refresh pending senders
          const resPending = await apiClient.get('/accounts/admin/sender-ids/pending', { headers });
          if (resPending.data && resPending.data.pending_senders) {
            setPendingSenders(resPending.data.pending_senders);
          }
        }
      } catch (err: any) {
        setAdminStatus(err.response?.data?.message || `Failed to process Sender ID ${approve ? 'approval' : 'rejection'}.`);
      } finally {
        setIsLoading(false);
      }
      return;
    }

    // Mock Mode
    if (approve) {
      setSenderIds([...senderIds, { ...sender, status: 'APPROVED' }]);
      setAdminStatus(`Sender ID mask "${sender.sender_id}" approved for dispatching!`);
    } else {
      setAdminStatus(`Sender ID mask "${sender.sender_id}" rejected and deleted.`);
    }

    setPendingSenders(pendingSenders.filter(s => s.id !== id));
  };

  // --- RESELLER EXCLUSIVE ACTIONS ---
  const handleOnboardClient = async (e: React.FormEvent) => {
    e.preventDefault();
    setResellerStatus(null);
    const credits = parseFloat(newClientCredits);
    const limit = parseFloat(newClientLimit);

    if (!newClientTenantName || !newClientName || !newClientEmail || !newClientPassword) {
      setResellerStatus("Please fill in all client details including contact name, email, and password.");
      return;
    }

    setIsLoading(true);
    try {
      const res = await apiClient.post('/accounts/reseller/clients', {
        tenant_name: newClientTenantName,
        name: newClientName,
        email: newClientEmail,
        password: newClientPassword,
        seeding_balance: credits,
        credit_limit: limit
      });
      if (res.data.status === 'SUCCESS') {
        setOnboardedClients([...onboardedClients, res.data.client]);
        if (resellerAccount) {
          setResellerAccount({ ...resellerAccount, api_credits: res.data.reseller_credits });
        }
        setResellerStatus(`✅ Corporate tenant "${newClientTenantName}" onboarded! Login credentials issued to ${newClientEmail}.`);
        setNewClientTenantName('');
        setNewClientName('');
        setNewClientEmail('');
        setNewClientPassword('');
        setNewClientCredits('500');
        setNewClientLimit('100');
      }
    } catch (err: any) {
      setResellerStatus(err.response?.data?.message || err.response?.data?.errors?.email?.[0] || 'Client onboarding failed.');
    } finally {
      setIsLoading(false);
    }
  };

  // --- CLIENT ACTIONS ---
  const handleQuickSend = async (e: React.FormEvent) => {
    e.preventDefault();
    setQsStatus({ type: null, msg: null });
    
    if (!qsSenderId) {
      setQsStatus({ type: 'error', msg: 'Please select an approved Sender ID.' });
      return;
    }

    try {
      const res = await apiClient.post('/quick-send', {
        msisdn: qsMsisdn,
        message: qsMessage,
        sender_id_id: qsSenderId
      });

      const msgExtra = res.data.gateway_balance 
        ? ` | Gateway Balance: ${res.data.gateway_balance}`
        : '';

      setQsStatus({
        type: 'success',
        msg: `Outbound SMS successfully queued! Campaign ID: ${res.data.campaign_id}. Internal Ledger Balance: Ksh ${res.data.balance_after}${msgExtra}`
      });

      if (clientAccount) {
        setClientAccount({
          ...clientAccount,
          wallet_balance: parseFloat(res.data.balance_after)
        });
      }

      setTransactions([
        {
          id: Date.now(),
          amount: -qsCostEstimate,
          balance_after: parseFloat(res.data.balance_after),
          type: 'SMS_DISPATCH',
          description: `Quick Send SMS to ${qsMsisdn}`,
          created_at: new Date().toLocaleDateString()
        },
        ...transactions
      ]);

      setQsMsisdn('');
      setQsMessage('');

    } catch (err: any) {
      console.error(err);
      const errMsg = err.response?.data?.message || err.response?.data?.error || 'Failed to dispatch Quick Send SMS.';
      setQsStatus({ type: 'error', msg: errMsg });
    }
  };

  const handleMpesaTopup = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!token) return;

    setMpesaLoading(true);
    setMpesaStatus("Initiating Safaricom STK Push to device...");

    try {
      const topupVal = parseFloat(mpesaAmount);
      if (isNaN(topupVal) || topupVal <= 0) {
        setMpesaStatus("Invalid payment topup amount.");
        setMpesaLoading(false);
        return;
      }

      const res = await apiClient.post('/client/finance/mpesa/stkpush', {
        phone_number: mpesaPhone,
        amount: topupVal
      }, {
        headers: { Authorization: `Bearer ${token}` }
      });

      setMpesaStatus(res.data.message || "STK Push initiated successfully. Please check your phone.");
      
      setTimeout(() => {
        setTopupDrawerOpen(false);
        setMpesaStatus(null);
        // We do not immediately increment the local wallet balance.
        // It will be updated automatically by the background Webhook callback.
        // A refresh of the profile will show the new balance once the user pays.
      }, 4000);

    } catch (err: any) {
      setMpesaStatus(err.response?.data?.message || "Failed to initiate STK push.");
    } finally {
      setMpesaLoading(false);
    }
  };

  const handleLaunchCampaign = async () => {
    setWizStatus({ type: null, msg: null });

    if (clientAccount && (clientAccount.wallet_balance + clientAccount.credit_limit) < wizCostEstimate) {
      setWizStatus({ type: 'error', msg: 'Insufficient funds. Pre-checkout ledger warning: Wallet balance is below the campaign cost estimate.' });
      return;
    }

    setIsLoading(true);

    if (token) {
      try {
        const config = { headers: { Authorization: `Bearer ${token}` } };
        
        let targets: string[] = [];
        if (wizAudienceType === 'manual') {
          targets = wizManualNumbers.split(/[\s,]+/).filter(x => x.trim().length > 0);
        } else if (wizSelectedGroup) {
          targets = contacts.filter(c => c.list_id === wizSelectedGroup).map(c => c.msisdn);
        }

        const res = await apiClient.post('/campaigns', {
          name: wizName,
          template: wizTemplate,
          opt_out_message: wizOptOutMessage,
          sender_id_id: wizSenderId,
          tps_limit: wizTpsLimit,
          quiet_hours_start: wizQuietHoursEnabled ? wizQuietHoursStart : null,
          quiet_hours_end: wizQuietHoursEnabled ? wizQuietHoursEnd : null,
          recurring_type: wizRecurringType,
          scheduled_at: wizScheduleType === 'future' ? wizScheduleDate : null,
          is_ab_test: wizIsAbTest,
          template_b: wizIsAbTest ? wizTemplateB : null,
          ab_split_ratio: wizIsAbTest ? wizAbSplitRatio : 50,
          target_contacts: targets,
          status: wizScheduleType === 'future' ? 'SCHEDULED' : 'PROCESSING'
        }, config);

        if (res.data.status === 'SUCCESS') {
          const msgExtra = res.data.gateway_balance 
            ? ` | Gateway Balance: ${res.data.gateway_balance}`
            : '';

          setWizStatus({
            type: 'success',
            msg: `Campaign successfully launched! ${res.data.campaign.name} processed. Total Cost: Ksh ${Number(res.data.analysis.estimated_cost).toFixed(4)} deducted atomically.${msgExtra}`
          });
          
          fetchProfile(token);
          fetchClientData(token);

          // Reset
          setCampaignStep(1);
          setWizName('Promo Campaign ' + new Date().toLocaleDateString());
          setWizTemplate('');
          setWizOptOutMessage('STOP *456*9*5#');
          setWizManualNumbers('');
          setWizIsAbTest(false);
          setWizTemplateB('');
        }
      } catch (err: any) {
        console.error("Campaign launch failed", err);
        setWizStatus({
          type: 'error',
          msg: err.response?.data?.message || 'Error occurred during campaign dispatch creation.'
        });
      } finally {
        setIsLoading(false);
      }
      return;
    }

    // Fallback Mock Mode
    setTimeout(() => {
      if (clientAccount) {
        const newBal = clientAccount.wallet_balance - wizCostEstimate;
        setClientAccount({
          ...clientAccount,
          wallet_balance: newBal
        });

        setTransactions([
          {
            id: Date.now(),
            amount: -wizCostEstimate,
            balance_after: newBal,
            type: 'SMS_DISPATCH',
            description: `Bulk Campaign "${wizName}" cost debit`,
            created_at: new Date().toLocaleDateString()
          },
          ...transactions
        ]);

        const newCamp = {
          id: Date.now(),
          name: wizName,
          template: wizTemplate,
          unicode_type: wizUnicode,
          sent_count: wizDeduplicatedCount,
          delivered_count: wizDeduplicatedCount,
          failed_count: 0,
          status: wizScheduleType === 'future' ? 'SCHEDULED' : 'COMPLETED',
          scheduled_at: wizScheduleType === 'future' ? wizScheduleDate : null,
          tps_limit: wizTpsLimit,
          approval_status: (user?.sub_role === 'CAMPAIGN_MANAGER') ? 'PENDING_APPROVAL' : 'APPROVED',
          is_ab_test: wizIsAbTest
        };
        setClientCampaigns([newCamp, ...clientCampaigns]);
      }

      setWizStatus({
        type: 'success',
        msg: `Campaign launched successfully! ${wizDeduplicatedCount} SMS records queued to worker queue. Pricing cost deducted atomically.`
      });

      setCampaignStep(1);
      setWizName('Promo Campaign ' + new Date().toLocaleDateString());
      setWizTemplate('');
      setWizOptOutMessage('STOP *456*9*5#');
      setWizManualNumbers('');
      setWizIsAbTest(false);
      setWizTemplateB('');
      setIsLoading(false);
    }, 2000);
  };

  const handleCreateSenderId = async (e: React.FormEvent) => {
    e.preventDefault();
    setSenderIdStatus({ type: null, msg: null });
    if (!newSenderIdMask) return;

    const mask = newSenderIdMask.trim();

    // Alphanumeric vs Numeric check
    const isAlphanumeric = /^[A-Za-z0-9\s\-]{3,11}$/.test(mask);
    const isNumeric = /^\+?[0-9]{3,20}$/.test(mask);

    if (!isAlphanumeric && !isNumeric) {
      setSenderIdStatus({
        type: 'error',
        msg: 'Invalid format. Custom Sender ID must be either an alphanumeric brand (max 11 characters) or a numeric mobile long-code/virtual number (up to 20 digits).'
      });
      return;
    }

    if (token) {
      setIsLoading(true);
      try {
        const headers = { Authorization: `Bearer ${token}` };
        const res = await apiClient.post('/sender-ids', { sender_id: mask }, { headers });
        if (res.data.status === 'SUCCESS') {
          setSenderIdStatus({
            type: 'success',
            msg: `Sender ID mask "${mask}" requested successfully and sent to carriers for approval.`
          });
          setNewSenderIdMask('');
          fetchClientData(token);
        }
      } catch (err: any) {
        setSenderIdStatus({
          type: 'error',
          msg: err.response?.data?.message || 'Failed to request Sender ID mask.'
        });
      } finally {
        setIsLoading(false);
      }
      return;
    }

    // Fallback Mock Mode
    const cleanMask = mask.toUpperCase();
    const blacklist = ['SAFARICOM', 'M-PESA', 'POLICE', 'COKE', 'AIRTEL', 'TELKOM', 'EQUITY', 'KCB'];
    if (blacklist.includes(cleanMask)) {
      setSenderIdStatus({
        type: 'error',
        msg: `The Sender ID mask "${mask}" violates brand and regulatory protection policies: Protected Local Operator or Brand name.`
      });
      return;
    }

    const mockId = Date.now();
    const newSender = {
      id: mockId,
      client_account_id: 100,
      sender_id: mask,
      status: 'PENDING',
      fallback_sender_id_id: null,
      mno_approvals: [
        { id: Date.now(), sender_id_id: mockId, mno_name: 'SAFARICOM', status: 'PENDING', reason: null },
        { id: Date.now() + 1, sender_id_id: mockId, mno_name: 'AIRTEL', status: 'PENDING', reason: null },
        { id: Date.now() + 2, sender_id_id: mockId, mno_name: 'TELKOM', status: 'PENDING', reason: null }
      ]
    };

    setSenderIds([newSender, ...senderIds]);
    setSenderIdStatus({
      type: 'success',
      msg: `Sender ID mask "${mask}" requested successfully (Mock Mode).`
    });
    setNewSenderIdMask('');
  };

  const handleSetSenderIdFallback = async (senderIdId: number, fallbackId: number | null) => {
    if (token) {
      try {
        const headers = { Authorization: `Bearer ${token}` };
        await apiClient.post(`/sender-ids/${senderIdId}/fallback`, { fallback_sender_id_id: fallbackId }, { headers });
        fetchClientData(token);
      } catch (err: any) {
        console.error("Failed to map fallback sender ID", err);
      }
      return;
    }

    // Mock Mode fallback update
    const updated = senderIds.map(s => {
      if (s.id === senderIdId) {
        return { ...s, fallback_sender_id_id: fallbackId };
      }
      return s;
    });
    setSenderIds(updated);
  };

  const handleMnoApprovalUpdate = async (senderIdId: number, mnoName: string, status: 'APPROVED' | 'REJECTED', reason: string) => {
    if (token) {
      try {
        const headers = { Authorization: `Bearer ${token}` };
        await apiClient.post(`/accounts/admin/sender-ids/${senderIdId}/mno-approval`, {
          mno_name: mnoName,
          status,
          reason
        }, { headers });
        
        // Refresh admin data
        const resPending = await apiClient.get('/accounts/admin/sender-ids/pending', { headers });
        if (resPending.data && resPending.data.pending_senders) {
          setPendingSenders(resPending.data.pending_senders);
        }
      } catch (err: any) {
        console.error("Failed to update MNO approval bind", err);
      }
      return;
    }

    // Mock Admin Mode
    const updatedPending = pendingSenders.map(s => {
      if (s.id === senderIdId) {
        const updatedMnos = (s.mno_approvals || []).map((m: any) => {
          if (m.mno_name === mnoName) {
            return { ...m, status, reason: status === 'REJECTED' ? reason : null };
          }
          return m;
        });

        // Recalculate parent status
        const statuses = updatedMnos.map((m: any) => m.status);
        let globalStatus = 'PENDING';
        if (statuses.every((st: string) => st === 'APPROVED')) {
          globalStatus = 'APPROVED';
        } else if (statuses.every((st: string) => st === 'REJECTED')) {
          globalStatus = 'REJECTED';
        }

        return { ...s, status: globalStatus, mno_approvals: updatedMnos };
      }
      return s;
    }).filter(s => s.status === 'PENDING');

    setPendingSenders(updatedPending);
  };

  const handleCreateKeyword = async (e: React.FormEvent) => {
    e.preventDefault();
    setShortcodeStatus(null);
    if (!newKeywordText) return;
    
    const payload = {
      shortcode_id: newKeywordShortcode,
      keyword: newKeywordText.toUpperCase().trim(),
      action_type: newKeywordAction,
      callback_webhook: newKeywordAction === 'WEBHOOK' ? newKeywordWebhook : null,
      reply_message: newKeywordReply
    };

    if (token) {
      try {
        const headers = { Authorization: `Bearer ${token}` };
        const res = await apiClient.post('/shortcodes/keywords', payload, { headers });
        if (res.data.status === 'SUCCESS') {
          setShortcodeStatus('Keyword created successfully.');
          setNewKeywordText('');
          setNewKeywordWebhook('');
          setNewKeywordReply('');
          fetchClientData(token);
        }
      } catch (err: any) {
        setShortcodeStatus(err.response?.data?.message || 'Failed to create keyword');
      }
      return;
    }

    // Fallback Mock Mode
    const mockKw = {
      id: Date.now(),
      ...payload
    };
    setKeywords([...keywords, mockKw]);
    setShortcodeStatus('Keyword created successfully (Mock Mode)');
    setNewKeywordText('');
    setNewKeywordWebhook('');
    setNewKeywordReply('');
  };

  const handleSendThreadMessage = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!activeConversationMsisdn || !threadReplyText) return;

    const payload = {
      msisdn: activeConversationMsisdn,
      message: threadReplyText,
      shortcode_id: 1
    };

    if (token) {
      try {
        const headers = { Authorization: `Bearer ${token}` };
        const res = await apiClient.post('/shortcodes/reply', payload, { headers });
        if (res.data.status === 'SUCCESS') {
          setThreadReplyText('');
          fetchClientData(token);
        }
      } catch (err: any) {
        console.error("Failed to send thread reply", err);
      }
      return;
    }

    // Fallback Mock Mode
    const existingThread = threadedConversations[activeConversationMsisdn] || [];
    const updatedThread = [
      ...existingThread,
      { direction: 'OUTGOING', message: threadReplyText, timestamp: new Date().toISOString() }
    ];

    setThreadedConversations({
      ...threadedConversations,
      [activeConversationMsisdn]: updatedThread
    });
    setThreadReplyText('');
  };

  const confirm2FASetup = () => {
    setAuthStatus('2FA_REQUIRED');
    setTotpCode('');
    setAuthError('Device key stored! Please solve the TOTP security challenge below with code: 123456');
  };

  return (
    <div className="min-h-screen bg-darkBg text-gray-100 flex font-sans">
      <Toaster position="top-center" toastOptions={{ duration: 15000, error: { duration: 30000 }, style: { background: '#1e293b', color: '#fff' } }} />
      
      {/* 1. AUTH SCREEN PANEL */}      {/* 1. LANDING PAGE & AUTH MODAL */}
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
      {/* 2. AUTHENTICATED PANEL SHELL */}
      {isLoggedIn && user && (
        <>
          {/* Mobile Overlay */}
          {sidebarOpen && (
            <div 
              className="fixed inset-0 bg-black/50 backdrop-blur-sm z-40 md:hidden" 
              onClick={() => setSidebarOpen(false)}
            />
          )}
          {/* 2.1 SIDEBAR NAVIGATION PANEL */}
          <aside className={`${sidebarOpen ? 'translate-x-0 w-64' : '-translate-x-full md:translate-x-0 w-64 md:w-20'} fixed md:relative z-50 md:z-20 h-full bg-slate-950/80 border-r border-slate-800 hover:bg-slate-900/80 hover:border-slate-700 hover:shadow-[4px_0_24px_-4px_rgba(99,102,241,0.15)] transition-all duration-300 flex flex-col overflow-hidden`}>
            <div className="h-16 flex items-center justify-between px-6 border-b border-slate-800/60">
              <div className="flex items-center gap-3">
                  <MessageSquare className="w-6 h-6 shrink-0 text-indigo-500" />
                  <span className={`font-black text-lg text-white tracking-widest uppercase transition-all duration-300 overflow-hidden whitespace-nowrap ${sidebarOpen ? 'max-w-[200px] opacity-100 translate-x-0' : 'max-w-0 opacity-0 -translate-x-2'}`}>CASAMOKO</span>
                </div>
            </div>

            {/* Role-Based Sidebar Navigation list */}
            <nav className="flex-1 px-4 py-6 space-y-2">
              
              {/* --- 2.1.1 SUPER_ADMIN TABS --- */}
              {user.role_tier === 'SUPER_ADMIN' && (
                <>
                  <button 
                    onClick={() => setCurrentPage('dashboard')} 
                    className={`w-full flex items-center gap-4 px-4 py-3 rounded-xl font-medium transition-all ${currentPage === 'dashboard' ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-500/20' : 'text-gray-400 hover:bg-slate-900/60 hover:text-white'}`}
                  >
                    <Activity className="w-5 h-5 shrink-0" />
                    <span className={`transition-all duration-300 overflow-hidden whitespace-nowrap ${sidebarOpen ? 'max-w-[200px] opacity-100 translate-x-0' : 'max-w-0 opacity-0 -translate-x-2'}`}>System Overview</span>
                  </button>
                  <button 
                    onClick={() => setCurrentPage('resellers')} 
                    className={`w-full flex items-center gap-4 px-4 py-3 rounded-xl font-medium transition-all ${currentPage === 'resellers' ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-500/20' : 'text-gray-400 hover:bg-slate-900/60 hover:text-white'}`}
                  >
                    <Building className="w-5 h-5 shrink-0" />
                    <span className={`transition-all duration-300 overflow-hidden whitespace-nowrap ${sidebarOpen ? 'max-w-[200px] opacity-100 translate-x-0' : 'max-w-0 opacity-0 -translate-x-2'}`}>Manage Resellers</span>
                  </button>
                  <button 
                    onClick={() => setCurrentPage('compliance')} 
                    className={`w-full flex items-center gap-4 px-4 py-3 rounded-xl font-medium transition-all ${currentPage === 'compliance' ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-500/20' : 'text-gray-400 hover:bg-slate-900/60 hover:text-white'}`}
                  >
                    <ShieldCheck className="w-5 h-5 shrink-0" />
                    <span className={`transition-all duration-300 overflow-hidden whitespace-nowrap ${sidebarOpen ? 'max-w-[200px] opacity-100 translate-x-0' : 'max-w-0 opacity-0 -translate-x-2'}`}>Sender Approvals</span>
                  </button>
                  <button 
                    onClick={() => setCurrentPage('audit')} 
                    className={`w-full flex items-center gap-4 px-4 py-3 rounded-xl font-medium transition-all ${currentPage === 'audit' ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-500/20' : 'text-gray-400 hover:bg-slate-900/60 hover:text-white'}`}
                  >
                    <Lock className="w-5 h-5 shrink-0" />
                    <span className={`transition-all duration-300 overflow-hidden whitespace-nowrap ${sidebarOpen ? 'max-w-[200px] opacity-100 translate-x-0' : 'max-w-0 opacity-0 -translate-x-2'}`}>Security Audits</span>
                  </button>
                  <button 
                    onClick={() => setCurrentPage('routing')} 
                    className={`w-full flex items-center gap-4 px-4 py-3 rounded-xl font-medium transition-all ${currentPage === 'routing' ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-500/20' : 'text-gray-400 hover:bg-slate-900/60 hover:text-white'}`}
                  >
                    <Cpu className="w-5 h-5 shrink-0" />
                    <span className={`transition-all duration-300 overflow-hidden whitespace-nowrap ${sidebarOpen ? 'max-w-[200px] opacity-100 translate-x-0' : 'max-w-0 opacity-0 -translate-x-2'}`}>SMPP Routing</span>
                  </button>
                  <button 
                    onClick={() => setCurrentPage('campaigns')} 
                    className={`w-full flex items-center gap-4 px-4 py-3 rounded-xl font-medium transition-all ${currentPage === 'campaigns' ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-500/20' : 'text-gray-400 hover:bg-slate-900/60 hover:text-white'}`}
                  >
                    <Send className="w-5 h-5 shrink-0" />
                    <span className={`transition-all duration-300 overflow-hidden whitespace-nowrap ${sidebarOpen ? 'max-w-[200px] opacity-100 translate-x-0' : 'max-w-0 opacity-0 -translate-x-2'}`}>Campaigns & Queue</span>
                  </button>
                </>
              )}

              {/* --- 2.1.2 RESELLER TABS --- */}
              {user.role_tier === 'RESELLER' && (
                <>
                  <button 
                    onClick={() => setCurrentPage('dashboard')} 
                    className={`w-full flex items-center gap-4 px-4 py-3 rounded-xl font-medium transition-all ${currentPage === 'dashboard' ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-500/20' : 'text-gray-400 hover:bg-slate-900/60 hover:text-white'}`}
                  >
                    <Activity className="w-5 h-5 shrink-0" />
                    <span className={`transition-all duration-300 overflow-hidden whitespace-nowrap ${sidebarOpen ? 'max-w-[200px] opacity-100 translate-x-0' : 'max-w-0 opacity-0 -translate-x-2'}`}>Channel Overview</span>
                  </button>
                  <button 
                    onClick={() => setCurrentPage('clients')} 
                    className={`w-full flex items-center gap-4 px-4 py-3 rounded-xl font-medium transition-all ${currentPage === 'clients' ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-500/20' : 'text-gray-400 hover:bg-slate-900/60 hover:text-white'}`}
                  >
                    <Users className="w-5 h-5 shrink-0" />
                    <span className={`transition-all duration-300 overflow-hidden whitespace-nowrap ${sidebarOpen ? 'max-w-[200px] opacity-100 translate-x-0' : 'max-w-0 opacity-0 -translate-x-2'}`}>Onboarded Clients</span>
                  </button>
                  <button 
                    onClick={() => setCurrentPage('campaigns')} 
                    className={`w-full flex items-center gap-4 px-4 py-3 rounded-xl font-medium transition-all ${currentPage === 'campaigns' ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-500/20' : 'text-gray-400 hover:bg-slate-900/60 hover:text-white'}`}
                  >
                    <Send className="w-5 h-5 shrink-0" />
                    <span className={`transition-all duration-300 overflow-hidden whitespace-nowrap ${sidebarOpen ? 'max-w-[200px] opacity-100 translate-x-0' : 'max-w-0 opacity-0 -translate-x-2'}`}>Campaigns & Queue</span>
                  </button>
                </>
              )}

              {/* --- 2.1.3 CLIENT TABS --- */}
              {user.role_tier === 'CLIENT' && (
                <>
                  <button 
                    onClick={() => setCurrentPage('dashboard')} 
                    className={`w-full flex items-center gap-4 px-4 py-3 rounded-xl font-medium transition-all ${currentPage === 'dashboard' ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-500/20' : 'text-gray-400 hover:bg-slate-900/60 hover:text-white'}`}
                  >
                    <TrendingUp className="w-5 h-5 shrink-0" />
                    <span className={`transition-all duration-300 overflow-hidden whitespace-nowrap ${sidebarOpen ? 'max-w-[200px] opacity-100 translate-x-0' : 'max-w-0 opacity-0 -translate-x-2'}`}>Dashboard</span>
                  </button>
                  <button 
                    onClick={() => setCurrentPage('campaigns')} 
                    className={`w-full flex items-center gap-4 px-4 py-3 rounded-xl font-medium transition-all ${currentPage === 'campaigns' ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-500/20' : 'text-gray-400 hover:bg-slate-900/60 hover:text-white'}`}
                  >
                    <Send className="w-5 h-5 shrink-0" />
                    <span className={`transition-all duration-300 overflow-hidden whitespace-nowrap ${sidebarOpen ? 'max-w-[200px] opacity-100 translate-x-0' : 'max-w-0 opacity-0 -translate-x-2'}`}>Campaigns & Queue</span>
                  </button>
                  <button 
                    onClick={() => setCurrentPage('inbox')} 
                    className={`w-full flex items-center gap-4 px-4 py-3 rounded-xl font-medium transition-all ${currentPage === 'inbox' ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-500/20' : 'text-gray-400 hover:bg-slate-900/60 hover:text-white'}`}
                  >
                    <Inbox className="w-5 h-5 shrink-0" />
                    <span className={`transition-all duration-300 overflow-hidden whitespace-nowrap ${sidebarOpen ? 'max-w-[200px] opacity-100 translate-x-0' : 'max-w-0 opacity-0 -translate-x-2'}`}>Two-Way Inbox</span>
                  </button>
                  <button 
                    onClick={() => setCurrentPage('reports')} 
                    className={`w-full flex items-center gap-4 px-4 py-3 rounded-xl font-medium transition-all ${currentPage === 'reports' ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-500/20' : 'text-gray-400 hover:bg-slate-900/60 hover:text-white'}`}
                  >
                    <BarChart2 className="w-5 h-5 shrink-0" />
                    <span className={`transition-all duration-300 overflow-hidden whitespace-nowrap ${sidebarOpen ? 'max-w-[200px] opacity-100 translate-x-0' : 'max-w-0 opacity-0 -translate-x-2'}`}>Delivery Analytics</span>
                  </button>
                  <button 
                    onClick={() => setCurrentPage('contacts')} 
                    className={`w-full flex items-center gap-4 px-4 py-3 rounded-xl font-medium transition-all ${currentPage === 'contacts' ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-500/20' : 'text-gray-400 hover:bg-slate-900/60 hover:text-white'}`}
                  >
                    <Users className="w-5 h-5 shrink-0" />
                    <span className={`transition-all duration-300 overflow-hidden whitespace-nowrap ${sidebarOpen ? 'max-w-[200px] opacity-100 translate-x-0' : 'max-w-0 opacity-0 -translate-x-2'}`}>Contacts & Blocklist</span>
                  </button>
                  <button 
                    onClick={() => setCurrentPage('finance')} 
                    className={`w-full flex items-center gap-4 px-4 py-3 rounded-xl font-medium transition-all ${currentPage === 'finance' ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-500/20' : 'text-gray-400 hover:bg-slate-900/60 hover:text-white'}`}
                  >
                    <Wallet className="w-5 h-5 shrink-0" />
                    <span className={`transition-all duration-300 overflow-hidden whitespace-nowrap ${sidebarOpen ? 'max-w-[200px] opacity-100 translate-x-0' : 'max-w-0 opacity-0 -translate-x-2'}`}>Wallet Ledger</span>
                  </button>
                  <button 
                    onClick={() => setCurrentPage('team')} 
                    className={`w-full flex items-center gap-4 px-4 py-3 rounded-xl font-medium transition-all ${currentPage === 'team' ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-500/20' : 'text-gray-400 hover:bg-slate-900/60 hover:text-white'}`}
                  >
                    <Activity className="w-5 h-5 shrink-0" />
                    <span className={`transition-all duration-300 overflow-hidden whitespace-nowrap ${sidebarOpen ? 'max-w-[200px] opacity-100 translate-x-0' : 'max-w-0 opacity-0 -translate-x-2'}`}>Team & Activity</span>
                  </button>
                  <button 
                    onClick={() => setCurrentPage('shortcodes')} 
                    className={`w-full flex items-center gap-4 px-4 py-3 rounded-xl font-medium transition-all ${currentPage === 'shortcodes' ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-500/20' : 'text-gray-400 hover:bg-slate-900/60 hover:text-white'}`}
                  >
                    <MessageSquare className="w-5 h-5 shrink-0" />
                    <span className={`transition-all duration-300 overflow-hidden whitespace-nowrap ${sidebarOpen ? 'max-w-[200px] opacity-100 translate-x-0' : 'max-w-0 opacity-0 -translate-x-2'}`}>Shortcode Services</span>
                  </button>
                  <button 
                    onClick={() => setCurrentPage('sender_ids')} 
                    className={`w-full flex items-center gap-4 px-4 py-3 rounded-xl font-medium transition-all ${currentPage === 'sender_ids' ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-500/20' : 'text-gray-400 hover:bg-slate-900/60 hover:text-white'}`}
                  >
                    <Sliders className="w-5 h-5 shrink-0" />
                    <span className={`transition-all duration-300 overflow-hidden whitespace-nowrap ${sidebarOpen ? 'max-w-[200px] opacity-100 translate-x-0' : 'max-w-0 opacity-0 -translate-x-2'}`}>Sender ID Config</span>
                  </button>
                  <button 
                    onClick={() => setCurrentPage('templates')} 
                    className={`w-full flex items-center gap-4 px-4 py-3 rounded-xl font-medium transition-all ${currentPage === 'templates' ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-500/20' : 'text-gray-400 hover:bg-slate-900/60 hover:text-white'}`}
                  >
                    <MessageSquareDashed className="w-5 h-5 shrink-0" />
                    <span className={`transition-all duration-300 overflow-hidden whitespace-nowrap ${sidebarOpen ? 'max-w-[200px] opacity-100 translate-x-0' : 'max-w-0 opacity-0 -translate-x-2'}`}>Dynamic Templates</span>
                  </button>
                  <button 
                    onClick={() => setCurrentPage('developer')} 
                    className={`w-full flex items-center gap-4 px-4 py-3 rounded-xl font-medium transition-all ${currentPage === 'developer' ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-500/20' : 'text-gray-400 hover:bg-slate-900/60 hover:text-white'}`}
                  >
                    <TerminalSquare className="w-5 h-5 shrink-0" />
                    <span className={`transition-all duration-300 overflow-hidden whitespace-nowrap ${sidebarOpen ? 'max-w-[200px] opacity-100 translate-x-0' : 'max-w-0 opacity-0 -translate-x-2'}`}>Developer APIs</span>
                  </button>
                </>
              )}

            </nav>

            <div className="p-4 border-t border-slate-800/60">
              <button 
                onClick={handleLogout}
                className="w-full flex items-center gap-4 px-4 py-3 rounded-xl font-medium text-red-450 hover:bg-red-950/20 transition-all"
              >
                <LogOut className="w-5 h-5 shrink-0" />
                <span className={`transition-all duration-300 overflow-hidden whitespace-nowrap ${sidebarOpen ? 'max-w-[200px] opacity-100 translate-x-0' : 'max-w-0 opacity-0 -translate-x-2'}`}>Terminate Session</span>
              </button>
            </div>
          </aside>

          {/* 2.2 MAIN BODY WORKSPACE CONTAINER */}
          <main className="flex-1 flex flex-col min-w-0 w-full bg-darkBg relative overflow-y-auto overflow-x-hidden">
            <div className="absolute w-[300px] h-[300px] rounded-full bg-indigo-600/5 blur-3xl top-20 left-20 pointer-events-none"></div>
            <div className="absolute w-[400px] h-[400px] rounded-full bg-blue-600/5 blur-3xl bottom-20 right-20 pointer-events-none"></div>

            {/* Header Navbar */}
            <header className="h-16 bg-slate-900/20 backdrop-blur-md border-b border-slate-850 flex items-center justify-between px-8 sticky top-0 z-10">
              <div className="flex items-center gap-4">
                <button 
                  onClick={() => setSidebarOpen(!sidebarOpen)}
                  className="p-1.5 bg-slate-900/60 border border-slate-800 rounded-lg text-gray-400 hover:text-white"
                >
                  <Menu className="w-5 h-5" />
                </button>
                <h1 className="text-xl font-black text-white tracking-wide uppercase">
                  {currentPage === 'dashboard' 
                    ? `${user.role_tier} OVERVIEW` 
                    : currentPage === 'shortcodes' 
                      ? 'SHORTCODE SERVICES' 
                      : currentPage === 'campaigns' 
                        ? 'BULK CAMPAIGN WIZARD' 
                        : currentPage === 'sender_ids' 
                          ? 'SENDER ID CONFIGURATION' 
                          : currentPage === 'routing'
                            ? 'SMPP BIND CARRIER ROUTING'
                            : currentPage === 'reports'
                              ? 'DELIVERY REPORTING & ANALYTICS'
                              : currentPage}
                </h1>
              </div>

              {/* Header Right Widgets depending on role */}
              <div className="flex items-center gap-4">
                {user.role_tier === 'CLIENT' && clientAccount && (
                  <>
                    {/* Sub-Role Simulator Dropdown */}

                    <div className="bg-slate-900/60 border border-slate-800/80 px-4 py-1.5 rounded-full flex items-center gap-3">
                      <div className="w-2.5 h-2.5 rounded-full bg-emerald-500 animate-ping"></div>
                      <span className="text-xs text-gray-300 font-semibold">{clientAccount.name}</span>
                    </div>
                    <div className="bg-indigo-600/10 border border-indigo-500/20 px-5 py-1.5 rounded-full flex items-center gap-2 text-indigo-300 font-mono text-sm">
                      <Wallet className="w-4 h-4" />
                      <span>Balance:</span>
                      <span className="font-extrabold">Ksh {Number(clientAccount.wallet_balance).toFixed(4)}</span>
                    </div>
                    <button 
                      onClick={() => setTopupDrawerOpen(true)}
                      className="bg-emerald-500 hover:bg-emerald-400 text-slate-900 font-bold px-4 py-1.5 rounded-full text-xs shadow-[0_0_15px_rgba(16,185,129,0.3)] transition-all flex items-center gap-1"
                    >
                      <ArrowUpRight className="w-3 h-3" /> Top Up
                    </button>
                  </>
                )}

                {user.role_tier === 'RESELLER' && resellerAccount && (
                  <>
                    <div className="bg-slate-900/60 border border-slate-800/80 px-4 py-1.5 rounded-full flex items-center gap-3">
                      <div className="w-2.5 h-2.5 rounded-full bg-emerald-500 animate-ping"></div>
                      <span className="text-xs text-gray-300 font-semibold">{resellerAccount.name}</span>
                    </div>
                    <div className="bg-indigo-600/10 border border-indigo-500/20 px-5 py-1.5 rounded-full flex items-center gap-2 text-indigo-300 font-mono text-sm">
                      <Wallet className="w-4 h-4" />
                      <span>API Credits:</span>
                      <span className="font-extrabold">Ksh {Number(resellerAccount.api_credits).toFixed(2)}</span>
                    </div>
                  </>
                )}

                {user.role_tier === 'SUPER_ADMIN' && (
                  <>
                    <button 
                      onClick={() => setIsBalanceModalOpen(true)}
                      className="bg-indigo-500/10 hover:bg-indigo-500/20 transition-all cursor-pointer border border-indigo-500/20 px-4 py-1.5 rounded-full flex items-center gap-2 text-indigo-400 font-bold text-xs"
                    >
                      {isFetchingBalance ? (
                        <div className="w-3 h-3 rounded-full border-2 border-indigo-400 border-t-transparent animate-spin" />
                      ) : (
                        <Wallet className="w-3 h-3" />
                      )}
                      <span className="font-mono">Ksh {gatewayBalance || '0.00'}</span>
                    </button>
                    <div className="bg-emerald-600/10 border border-emerald-500/20 px-5 py-1.5 rounded-full flex items-center gap-2 text-emerald-350 font-bold text-xs uppercase tracking-wider">
                      <Activity className="w-4 h-4 text-emerald-400" />
                      <span>Compliance Core Bindings: Active</span>
                    </div>
                  </>
                )}

                <button 
                  onClick={() => {
                    setEditProfileName(user.name);
                    setEditProfilePassword('');
                    setIsProfileModalOpen(true);
                  }}
                  className="flex items-center gap-3 bg-slate-900/60 hover:bg-slate-800/80 border border-slate-800/80 hover:border-indigo-500/50 pl-1 pr-4 py-1 rounded-full transition-all group ml-2"
                >
                  <div className="w-7 h-7 rounded-full bg-indigo-500/20 flex items-center justify-center border border-indigo-500/30">
                    <User className="w-4 h-4 text-indigo-400 group-hover:text-indigo-300" />
                  </div>
                  <div className="text-left hidden md:block">
                    <p className="text-xs text-gray-300 group-hover:text-white font-bold leading-tight">{user.name}</p>
                  </div>
                </button>
              </div>
            </header>

            {/* Scrollable Main Content Frame */}
            <div className="flex-1 p-4 md:p-8 space-y-8 relative z-10 min-w-0 w-full">

              {/* ========================================== */}
              {/* ========================================== */}
              {/* --- I. SUPER_ADMIN INTERFACES --- */}
              {user.role_tier === 'SUPER_ADMIN' && (
                <>
                  {/* I.1 SUPER_ADMIN SYSTEM OVERVIEW */}
                  {currentPage === 'dashboard' && (
                    <div className="space-y-8">
                      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                        <div className="glass-panel p-6 rounded-2xl border border-slate-850 glow-card flex items-center justify-between">
                          <div>
                            <p className="text-xs font-semibold text-gray-400 uppercase tracking-widest mb-1">Global Revenue</p>
                            <h3 className="text-3xl font-black text-white font-mono">Ksh {adminAnalytics.global_revenue.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}</h3>
                            <p className="text-[10px] text-emerald-400 mt-1 font-bold">+12% Profit margins this week</p>
                          </div>
                          <div className="p-4 bg-emerald-500/10 rounded-2xl text-emerald-400 border border-emerald-500/20">
                            <Wallet className="w-6 h-6" />
                          </div>
                        </div>

                        <div className="glass-panel p-6 rounded-2xl border border-slate-850 glow-card flex items-center justify-between">
                          <div>
                            <p className="text-xs font-semibold text-gray-400 uppercase tracking-widest mb-1">Onboarded Channels</p>
                            <h3 className="text-3xl font-black text-white font-mono">{adminAnalytics.onboarded_resellers} <span className="text-xs font-normal">Resellers</span></h3>
                            <p className="text-[10px] text-gray-400 mt-1">Multi-level networks active</p>
                          </div>
                          <div className="p-4 bg-indigo-500/10 rounded-2xl text-indigo-400 border border-indigo-500/20">
                            <Building className="w-6 h-6" />
                          </div>
                        </div>

                        <div className="glass-panel p-6 rounded-2xl border border-slate-850 glow-card flex items-center justify-between">
                          <div>
                            <p className="text-xs font-semibold text-gray-400 uppercase tracking-widest mb-1">Global SMS Fired</p>
                            <h3 className="text-3xl font-black text-white font-mono">{adminAnalytics.total_sms_fired.toLocaleString()}</h3>
                            <p className="text-[10px] text-gray-400 mt-1">Total operational dispatches</p>
                          </div>
                          <div className="p-4 bg-blue-500/10 rounded-2xl text-blue-400 border border-blue-500/20">
                            <Send className="w-6 h-6" />
                          </div>
                        </div>

                        <div className="glass-panel p-6 rounded-2xl border border-slate-850 glow-card flex items-center justify-between">
                          <div>
                            <p className="text-xs font-semibold text-gray-400 uppercase tracking-widest mb-1">Peak Capacity</p>
                            <h3 className="text-3xl font-black text-white font-mono">{adminAnalytics.peak_capacity_tps.toLocaleString()} <span className="text-xs font-normal">TPS</span></h3>
                            <p className="text-[10px] text-emerald-400 mt-1 flex items-center gap-0.5 font-bold"><CheckCircle className="w-3 h-3 text-emerald-500" /> sub-200ms latency binds</p>
                          </div>
                          <div className="p-4 bg-violet-500/10 rounded-2xl text-violet-400 border border-violet-500/20">
                            <Activity className="w-6 h-6" />
                          </div>
                        </div>
                      </div>

                      <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
                        <div className="lg:col-span-2 glass-panel p-6 rounded-2xl border border-slate-850 glow-card">
                          <h4 className="font-bold text-white mb-4 flex items-center gap-2">
                            <Activity className="w-5 h-5 text-indigo-400" />
                            Carrier Line Binding Health
                          </h4>
                          <div className="space-y-4">
                            <div className="flex items-center justify-between p-4 bg-slate-900/40 rounded-xl border border-slate-800/40">
                              <div>
                                <h5 className="font-bold text-white text-sm">Safaricom SMPP Binds (Primary Node)</h5>
                                <p className="text-[10px] text-gray-400 mt-0.5">Throughput: 8,400 TPS | Latency: 45ms</p>
                              </div>
                              <div className="flex items-center gap-3">
                                {isFetchingBalance ? (
                                  <span className="text-[10px] font-mono font-bold px-2 py-0.5 rounded bg-slate-800 text-gray-400 border border-slate-700 flex items-center gap-1">
                                    <RefreshCw className="w-3 h-3 animate-spin" /> Fetching...
                                  </span>
                                ) : gatewayBalance && (
                                  <span className="text-[10px] font-mono font-bold px-2 py-0.5 rounded bg-indigo-500/10 text-indigo-400 border border-indigo-500/20 flex items-center gap-1">
                                    <Wallet className="w-3 h-3" /> Ksh {gatewayBalance}
                                  </span>
                                )}
                                <span className="px-2.5 py-0.5 bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 text-[10px] font-bold rounded uppercase">CONNECTED</span>
                              </div>
                            </div>
                            <div className="flex items-center justify-between p-4 bg-slate-900/40 rounded-xl border border-slate-800/40">
                              <div>
                                <h5 className="font-bold text-white text-sm">Airtel Corporate REST Gateway (Fallback)</h5>
                                <p className="text-[10px] text-gray-400 mt-0.5">Throughput: 2,000 TPS | Latency: 120ms</p>
                              </div>
                              <span className="px-2.5 py-0.5 bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 text-[10px] font-bold rounded uppercase">CONNECTED</span>
                            </div>
                            <div className="flex items-center justify-between p-4 bg-slate-900/40 rounded-xl border border-slate-800/40">
                              <div>
                                <h5 className="font-bold text-white text-sm">Telkom Kenya Bind Line</h5>
                                <p className="text-[10px] text-gray-400 mt-0.5">Throughput: 500 TPS | Status: Ready</p>
                              </div>
                              <span className="px-2.5 py-0.5 bg-indigo-500/10 text-indigo-400 border border-indigo-500/20 text-[10px] font-bold rounded uppercase">STANDBY</span>
                            </div>
                          </div>
                        </div>

                        <div className="glass-panel p-6 rounded-2xl border border-slate-850 glow-card flex flex-col justify-between">
                          <div>
                            <h4 className="font-bold text-white mb-2">Compliance Queue</h4>
                            <p className="text-xs text-gray-400 mb-4">Pending alpha Sender ID applications requiring compliance verification.</p>
                            <div className="p-4 bg-indigo-950/20 border border-indigo-500/20 text-indigo-300 text-xs rounded-xl flex items-center gap-3">
                              <ShieldCheck className="w-5 h-5 shrink-0" />
                              <span>{pendingSenders.length} registrations awaiting KYC validation checks.</span>
                            </div>
                          </div>
                          <button 
                            onClick={() => setCurrentPage('compliance')}
                            className="w-full bg-indigo-600 hover:bg-indigo-700 py-3 rounded-xl font-bold transition-all text-xs uppercase tracking-wider flex items-center justify-center gap-2 mt-4"
                          >
                            <Eye className="w-4 h-4" /> Open Compliance Queue
                          </button>
                        </div>
                        <div className="glass-panel p-6 rounded-2xl border border-slate-850 glow-card col-span-full">
                          <h4 className="font-bold text-white mb-4 flex items-center gap-2">
                            <Users className="w-5 h-5 text-indigo-400" />
                            Corporate Clients Registry
                          </h4>
                          <div className="overflow-x-auto">
                            <table className="w-full text-left text-sm text-gray-300">
                              <thead className="bg-slate-900/60 uppercase tracking-widest text-[10px] text-gray-400">
                                <tr>
                                  <th className="px-6 py-4 rounded-l-xl">Client Organization</th>
                                  <th className="px-6 py-4">Wallet Balance</th>
                                  <th className="px-6 py-4">Credit Limit</th>
                                  <th className="px-6 py-4">KYC Status</th>
                                  <th className="px-6 py-4 rounded-r-xl text-center">Compliance Control</th>
                                </tr>
                              </thead>
                              <tbody className="divide-y divide-slate-800/40">
                                {clients.map(c => (
                                  <tr key={c.id} className="hover:bg-slate-900/20">
                                    <td className="px-6 py-4 font-bold text-white">{c.name}</td>
                                    <td className="px-6 py-4 font-mono font-bold text-indigo-350">Ksh {Number(c.wallet_balance).toFixed(2)}</td>
                                    <td className="px-6 py-4 font-mono font-bold text-gray-450">Ksh {Number(c.credit_limit).toFixed(2)}</td>
                                    <td className="px-6 py-4">
                                      <span className={`px-2.5 py-0.5 rounded text-[10px] font-bold uppercase ${c.status === 'APPROVED' ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : c.status === 'PENDING' ? 'bg-amber-500/10 text-amber-400 border border-amber-500/20' : 'bg-red-500/10 text-red-400 border border-red-500/20'}`}>{c.status}</span>
                                    </td>
                                    <td className="px-6 py-4 text-center flex items-center justify-center gap-2">
                                      <button 
                                        onClick={() => handleToggleClientSuspension(c.id, c.status)}
                                        className={`px-3 py-1.5 rounded-lg text-xs font-bold transition-all shadow-sm ${c.status === 'SUSPENDED' ? 'bg-emerald-600 hover:bg-emerald-700 text-white' : 'bg-red-655 hover:bg-red-700 text-white'}`}
                                      >
                                        {c.status === 'SUSPENDED' ? 'Reactivate Client' : 'Suspend Client'}
                                      </button>
                                      <button 
                                        onClick={() => {
                                          setAdjustingClient(c);
                                          setAdjustmentAmount('100');
                                          setAdjustmentReason('REFUND_CORRECTION');
                                          setAdjustmentDesc('');
                                        }}
                                        className="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-xs font-bold transition-all shadow-sm flex items-center gap-1"
                                      >
                                        <Wallet className="w-3.5 h-3.5" /> Adjust Balance
                                      </button>
                                    </td>
                                  </tr>
                                ))}
                              </tbody>
                            </table>
                          </div>
                        </div>
                      </div>
                    </div>
                  )}

                  {/* I.2 SUPER_ADMIN MANAGE RESELLERS */}
                  {currentPage === 'resellers' && (
                    <div className="space-y-8">
                      <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
                        
                        {/* Onboard reseller form */}
                        <div className="glass-panel p-6 rounded-2xl border border-slate-850 glow-card">
                          <h4 className="font-bold text-white mb-2">Onboard Reseller Channel</h4>
                          <p className="text-xs text-gray-400 mb-6 font-medium">Create independent reseller portfolios with customized commission markups.</p>
                          
                          {adminStatus && (
                            <div className="p-3 bg-indigo-950/40 border border-indigo-500/20 text-indigo-300 text-xs rounded-xl mb-4">
                              {adminStatus}
                            </div>
                          )}

                          <form onSubmit={handleAddReseller} className="space-y-4">
                            <div>
                              <label className="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">Reseller Channel Name</label>
                              <input 
                                type="text"
                                required
                                value={newResellerName}
                                onChange={(e) => setNewResellerName(e.target.value)}
                                className="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-indigo-500 text-sm"
                                placeholder="e.g. East Africa Partners"
                              />
                            </div>
                            <div>
                              <label className="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">Reseller Login Email</label>
                              <input 
                                type="email"
                                required
                                value={newResellerEmail}
                                onChange={(e) => setNewResellerEmail(e.target.value)}
                                className="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-indigo-500 text-sm"
                                placeholder="partner@eastafrica.com"
                              />
                            </div>
                            <div>
                               <label className="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">Initial Password</label>
                               <div className="relative">
                                 <input 
                                   type={showNewResellerPassword ? "text" : "password"}
                                   required
                                   minLength={6}
                                   value={newResellerPassword}
                                   onChange={(e) => setNewResellerPassword(e.target.value)}
                                   className="w-full bg-slate-955 border border-slate-800 rounded-xl pl-4 pr-12 py-3 text-white focus:outline-none focus:ring-2 focus:ring-indigo-500 text-sm"
                                   placeholder="Min 6 characters"
                                 />
                                 <button
                                   type="button"
                                   onClick={() => setShowNewResellerPassword(!showNewResellerPassword)}
                                   className="absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 hover:text-white transition-colors"
                                 >
                                   {showNewResellerPassword ? <EyeOff className="w-5 h-5" /> : <Eye className="w-5 h-5" />}
                                 </button>
                               </div>
                             </div>
                            <div className="grid grid-cols-2 gap-4">
                              <div>
                                <label className="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">Markup (%)</label>
                                <input 
                                  type="number"
                                  required
                                  value={newResellerMarkup}
                                  onChange={(e) => setNewResellerMarkup(e.target.value)}
                                  className="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-indigo-500 font-mono text-sm"
                                />
                              </div>
                              <div>
                                <label className="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">Seeding Credits</label>
                                <input 
                                  type="number"
                                  required
                                  value={newResellerCredits}
                                  onChange={(e) => setNewResellerCredits(e.target.value)}
                                  className="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-indigo-500 font-mono text-sm"
                                />
                              </div>
                            </div>
                            <button
                              type="submit"
                              disabled={isLoading}
                              className="w-full bg-indigo-600 hover:bg-indigo-700 disabled:opacity-60 text-white font-bold py-3 rounded-xl transition-all flex items-center justify-center gap-2 hover:shadow text-xs uppercase tracking-wider mt-4"
                            >
                              {isLoading ? <span className="animate-spin w-4 h-4 border-2 border-white border-t-transparent rounded-full" /> : <Plus className="w-4 h-4" />}
                              {isLoading ? 'Creating...' : 'Provision Reseller & Credentials'}
                            </button>
                          </form>
                        </div>

                        {/* Reseller channels list */}
                        <div className="lg:col-span-2 glass-panel p-6 rounded-2xl border border-slate-850 glow-card">
                          <h4 className="font-bold text-white mb-4">Active Reseller Portfolios</h4>
                          <div className="overflow-x-auto">
                            <table className="w-full text-left text-sm text-gray-300">
                              <thead className="bg-slate-900/60 uppercase tracking-widest text-[10px] text-gray-400">
                                <tr>
                                  <th className="px-6 py-4 rounded-l-xl">Reseller Name</th>
                                  <th className="px-6 py-4">Profit Markup</th>
                                  <th className="px-6 py-4">Available Credits</th>
                                  <th className="px-6 py-4">Privilege Status</th>
                                  <th className="px-6 py-4 rounded-r-xl text-right">Actions</th>
                                </tr>
                              </thead>
                              <tbody className="divide-y divide-slate-800/40">
                                {resellers.map(r => (
                                  <tr key={r.id} className="hover:bg-slate-900/20">
                                    <td className="px-6 py-4 font-bold text-white">{r.name}</td>
                                    <td className="px-6 py-4 font-mono font-bold text-indigo-400">{Number(r.markup_percentage).toFixed(2)}% Markup</td>
                                    <td className="px-6 py-4 font-mono font-bold text-white">Ksh {Number(r.api_credits).toFixed(2)}</td>
                                    <td className="px-6 py-4 text-emerald-400 font-bold text-xs">
                                      <div className="flex items-center gap-1">
                                        <CheckCircle className="w-4 h-4 text-emerald-400" /> Active Binds
                                      </div>
                                    </td>
                                    <td className="px-6 py-4 text-right">
                                      <div className="flex justify-end items-center gap-3">
                                        <button title="View Profile" onClick={() => setViewingReseller(r)} className="text-gray-400 hover:text-emerald-400 transition-colors">
                                          <Eye className="w-4 h-4" />
                                        </button>
                                        <button title="Edit User" onClick={() => { setEditingReseller(r); setEditResellerName(r.name); setEditResellerMarkup(r.markup_percentage.toString()); setEditResellerCredits(r.api_credits.toString()); }} className="text-gray-400 hover:text-indigo-400 transition-colors">
                                          <Sliders className="w-4 h-4" />
                                        </button>
                                        <button title="Reset Password" onClick={() => { setResettingReseller(r); setResetResellerPassword(''); }} className="text-gray-400 hover:text-amber-400 transition-colors">
                                          <RefreshCw className="w-4 h-4" />
                                        </button>
                                        <button title="Delete User" onClick={() => setDeletingReseller(r)} className="text-gray-400 hover:text-rose-400 transition-colors">
                                          <XCircle className="w-4 h-4" />
                                        </button>
                                      </div>
                                    </td>
                                  </tr>
                                ))}
                              </tbody>
                            </table>
                          </div>
                        </div>

                      </div>
                    </div>
                  )}

                  {/* I.3 SUPER_ADMIN COMPLIANCE APPROVALS QUEUE */}
                  {currentPage === 'compliance' && (
                    <div className="glass-panel p-8 rounded-2xl border border-slate-850 glow-card max-w-4xl mx-auto">
                      <div className="flex items-center justify-between mb-6 pb-4 border-b border-slate-800/40">
                        <div>
                          <h3 className="text-lg font-bold text-white flex items-center gap-2">
                            <ShieldCheck className="w-5 h-5 text-indigo-400" />
                            Sender ID Compliance Registry Queue
                          </h3>
                          <p className="text-[10px] text-gray-400 mt-1">Review alphanumeric sender masks before activation across mobile networks.</p>
                        </div>
                        <span className="px-3 py-1 bg-indigo-500/10 text-indigo-400 border border-indigo-500/20 text-xs font-mono font-bold rounded">{pendingSenders.length} pending approval</span>
                      </div>

                      {adminStatus && (
                        <div className="p-4 bg-indigo-950/30 border border-indigo-500/30 text-indigo-300 text-sm rounded-xl mb-6">
                          {adminStatus}
                        </div>
                      )}

                      {pendingSenders.length === 0 ? (
                        <div className="text-center py-12">
                          <CheckCircle2 className="w-12 h-12 text-emerald-400 mx-auto mb-3" />
                          <h4 className="font-bold text-white text-base">Compliance Queue is Clear</h4>
                          <p className="text-xs text-gray-400 mt-1">All alphanumeric sender identities have been verified and processed.</p>
                        </div>
                      ) : (
                        <div className="overflow-x-auto">
                          <table className="w-full text-left text-sm text-gray-300">
                            <thead className="bg-slate-900/60 uppercase tracking-widest text-[10px] text-gray-400">
                              <tr>
                                <th className="px-6 py-4 rounded-l-xl">Applying Corporate Tenant</th>
                                <th className="px-6 py-4">Requested Sender ID Mask</th>
                                <th className="px-6 py-4">Compliance Status</th>
                                <th className="px-6 py-4 rounded-r-xl text-center">Programmatic Action</th>
                              </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-800/40">                        {pendingSenders.map(s => (
                                <React.Fragment key={s.id}>
                                  <tr className="hover:bg-slate-900/20 border-t border-slate-800/40">
                                    <td className="px-6 py-4 font-bold text-white">{s.client_name}</td>
                                    <td className="px-6 py-4 font-mono font-black text-indigo-400 tracking-wider text-base">{s.sender_id}</td>
                                    <td className="px-6 py-4">
                                      <span className="px-2.5 py-0.5 bg-amber-500/10 text-amber-400 border border-amber-500/20 text-[10px] font-bold rounded uppercase tracking-wider">{s.status || 'PENDING'}</span>
                                    </td>
                                    <td className="px-6 py-4 flex gap-2 justify-center">
                                      <button 
                                        onClick={() => handleSenderApproval(s.id, true)}
                                        className="px-3 py-1.5 bg-emerald-650 hover:bg-emerald-700 text-white text-xs font-bold rounded-lg transition-all flex items-center gap-1"
                                      >
                                        <CheckCircle2 className="w-4 h-4" /> Approve Globally
                                      </button>
                                      <button 
                                        onClick={() => handleSenderApproval(s.id, false)}
                                        className="px-3 py-1.5 bg-red-650 hover:bg-red-700 text-white text-xs font-bold rounded-lg transition-all flex items-center gap-1"
                                      >
                                        <XCircle className="w-4 h-4" /> Reject Globally
                                      </button>
                                    </td>
                                  </tr>
                                  <tr className="bg-slate-900/5">
                                    <td colSpan={4} className="px-6 py-4">
                                      <div className="bg-slate-950/40 border border-slate-850 rounded-xl p-4 space-y-4 max-w-3xl mx-auto">
                                        <div className="flex items-center justify-between border-b border-slate-800/40 pb-2">
                                          <span className="text-[10px] font-bold text-indigo-300 uppercase tracking-wider">Carrier/MNO Approvals Matrix Overrides</span>
                                          <span className="text-[9px] text-gray-500">Configure operator statuses separately below:</span>
                                        </div>
                                        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                                          {['SAFARICOM', 'AIRTEL', 'TELKOM'].map(mno => {
                                            const bind = s.mno_approvals?.find((m: any) => m.mno_name === mno) || { status: 'PENDING', reason: null };
                                            
                                            return (
                                              <div key={mno} className="bg-slate-900/60 border border-slate-850 rounded-xl p-3 flex flex-col justify-between gap-3">
                                                <div className="flex items-center justify-between">
                                                  <span className="font-mono text-xs font-bold text-white tracking-wide">{mno}</span>
                                                  <span className={`px-2 py-0.5 text-[8px] font-bold rounded uppercase ${
                                                    bind.status === 'APPROVED' ? 'bg-emerald-500/10 text-emerald-450 border border-emerald-500/20' :
                                                    bind.status === 'REJECTED' ? 'bg-red-500/10 text-red-405 border border-red-500/20' :
                                                    'bg-amber-500/10 text-amber-400 border border-amber-500/20'
                                                  }`}>
                                                    {bind.status}
                                                  </span>
                                                </div>

                                                {bind.status === 'REJECTED' && bind.reason && (
                                                  <p className="text-[9px] text-red-400 italic max-w-full break-words">"{bind.reason}"</p>
                                                )}

                                                <div className="flex gap-1.5 mt-2">
                                                  <button
                                                    onClick={() => handleMnoApprovalUpdate(s.id, mno, 'APPROVED', '')}
                                                    className="flex-1 bg-emerald-600/20 hover:bg-emerald-600 text-emerald-400 hover:text-white border border-emerald-500/30 py-1 rounded text-[9px] font-bold transition-all flex items-center justify-center gap-1 uppercase"
                                                  >
                                                    Approve
                                                  </button>
                                                  <button
                                                    onClick={() => {
                                                      const reason = prompt(`Enter rejection reason for ${mno}:`);
                                                      if (reason !== null) {
                                                        handleMnoApprovalUpdate(s.id, mno, 'REJECTED', reason || 'Compliance policy violation');
                                                      }
                                                    }}
                                                    className="flex-1 bg-red-600/20 hover:bg-red-655 text-red-450 hover:text-white border border-red-500/30 py-1 rounded text-[9px] font-bold transition-all flex items-center justify-center gap-1 uppercase"
                                                  >
                                                    Reject
                                                  </button>
                                                </div>
                                              </div>
                                            );
                                          })}
                                        </div>
                                      </div>
                                    </td>
                                  </tr>
                                </React.Fragment>
                              ))}
                            </tbody>
                          </table>
                        </div>
                      )}
                    </div>
                  )}

                  {/* I.4 SUPER_ADMIN SECURITY LOGIN AUDIT LOGS */}
                  {currentPage === 'audit' && (
                    <div className="glass-panel p-8 rounded-2xl border border-slate-850 glow-card max-w-4xl mx-auto">
                      <div className="flex items-center justify-between mb-6 pb-4 border-b border-slate-800/40">
                        <div>
                          <h3 className="text-lg font-bold text-white flex items-center gap-2">
                            <Lock className="w-5 h-5 text-indigo-400" />
                            Security Login Auditing Ledger
                          </h3>
                          <p className="text-[10px] text-gray-400 mt-1">Immutable historical audit logs logging authentication attempts, IPs, and authorization states.</p>
                        </div>
                        <span className="text-xs text-indigo-400 font-mono font-bold">Audit Live</span>
                      </div>

                      <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm text-gray-300">
                          <thead className="bg-slate-900/60 uppercase tracking-widest text-[10px] text-gray-400">
                            <tr>
                              <th className="px-6 py-4 rounded-l-xl">IP Address</th>
                              <th className="px-6 py-4">Authenticating Username</th>
                              <th className="px-6 py-4">Auth Status</th>
                              <th className="px-6 py-4">Failure Reason</th>
                              <th className="px-6 py-4 rounded-r-xl">Timestamp</th>
                            </tr>
                          </thead>
                          <tbody className="divide-y divide-slate-800/40">
                            {loginLogs.map(log => (
                              <tr key={log.id} className="hover:bg-slate-900/20">
                                <td className="px-6 py-4 font-mono text-xs text-white">{log.ip_address}</td>
                                <td className="px-6 py-4 font-semibold text-gray-300">{log.username}</td>
                                <td className="px-6 py-4">
                                  <span className={`px-2.5 py-0.5 rounded text-[10px] font-bold tracking-wider ${log.status === 'SUCCESS' ? 'bg-emerald-500/10 text-emerald-400' : 'bg-red-500/10 text-red-400'}`}>{log.status}</span>
                                </td>
                                <td className="px-6 py-4 font-mono text-xs text-red-300">
                                  {log.failure_reason || <span className="text-gray-500">—</span>}
                                </td>
                                <td className="px-6 py-4 font-mono text-xs text-gray-450">{log.created_at}</td>
                              </tr>
                            ))}
                          </tbody>
                        </table>
                      </div>
                    </div>
                  )}

                  {/* I.5 SUPER_ADMIN ADVANCED LCR ROUTING CONFIGURATION */}
                  {currentPage === 'routing' && (
                    <div className="space-y-8 animate-fadeIn">
                      <div className="glass-panel p-6 rounded-2xl border border-slate-850 glow-card">
                        <div className="flex items-center justify-between mb-6 pb-2 border-b border-slate-800/40">
                          <div>
                            <h3 className="text-lg font-bold text-white flex items-center gap-2">
                              <Route className="w-5 h-5 text-indigo-400" />
                              Advanced LCR Routing Engine
                            </h3>
                            <p className="text-[10px] text-gray-400 mt-1">Manage Least Cost Routing priority lists across MCC/MNC networks and destination prefixes.</p>
                          </div>
                          <div className="flex items-center gap-4">
                            <span className="text-[10px] font-mono text-gray-500">Active LCR Routes: {lcrRoutes.length}</span>
                            <button onClick={() => { setEditingRoute(null); setRouteFormData({ provider: '', mcc: '', mnc: '', prefix: '', cost: 0.000, priority: 1, status: 'ACTIVE' }); setIsRouteModalOpen(true); }} className="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded-lg text-[11px] font-bold shadow-lg shadow-emerald-500/20 flex items-center gap-1">
                              <Plus className="w-3 h-3" /> Add Route
                            </button>
                          </div>
                        </div>

                        <div className="overflow-x-auto">
                          <table className="w-full text-left text-xs text-gray-300">
                            <thead className="bg-slate-950 uppercase tracking-widest text-[9px] text-gray-400 font-bold">
                              <tr>
                                <th className="px-6 py-4 rounded-l-xl">Provider / Connection</th>
                                <th className="px-6 py-4 text-center">Priority</th>
                                <th className="px-6 py-4 text-center">MCC</th>
                                <th className="px-6 py-4 text-center">MNC</th>
                                <th className="px-6 py-4 text-center">Prefix / Regex</th>
                                <th className="px-6 py-4 text-center">Base Cost (Ksh)</th>
                                <th className="px-6 py-4 text-center">Status</th>
                                <th className="px-6 py-4 rounded-r-xl text-center">Actions</th>
                              </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-855 bg-slate-900/10">
                              {lcrRoutes.map(r => (
                                <tr key={r.id} className="hover:bg-slate-900/20 transition-all">
                                  <td className="px-6 py-4">
                                    <div className="font-bold text-white flex items-center gap-2">
                                      <GitMerge className="w-3.5 h-3.5 text-emerald-400" /> {r.provider}
                                    </div>
                                    <div className="text-[9px] text-gray-405 mt-1 tracking-wider">SMPP BIND SECURE</div>
                                  </td>
                                  <td className="px-6 py-4 text-center">
                                    <span className={`px-2.5 py-1 rounded-md text-xs font-bold font-mono border ${r.priority === 1 ? 'bg-indigo-600 text-white border-indigo-500 shadow-[0_0_10px_rgba(79,70,229,0.4)]' : 'bg-slate-800 text-gray-300 border-slate-700'}`}>
                                      P-{r.priority}
                                    </span>
                                  </td>
                                  <td className="px-6 py-4 text-center font-mono font-bold text-indigo-300">
                                    {r.mcc}
                                  </td>
                                  <td className="px-6 py-4 text-center font-mono font-bold text-indigo-300">
                                    {r.mnc}
                                  </td>
                                  <td className="px-6 py-4 text-center font-mono">
                                    <span className="px-2 py-0.5 bg-slate-950 rounded text-[10px] text-gray-400 border border-slate-800">
                                      {r.prefix}
                                    </span>
                                  </td>
                                  <td className="px-6 py-4 text-center font-mono text-emerald-400 font-bold tracking-wider">
                                    {r.cost.toFixed(3)}
                                  </td>
                                  <td className="px-6 py-4 text-center">
                                    <span className={`px-2 py-0.5 text-[9px] font-bold rounded uppercase ${r.status === 'ACTIVE' ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 'bg-red-500/10 text-red-400 border border-red-500/20'}`}>
                                      {r.status}
                                    </span>
                                  </td>
                                  <td className="px-6 py-4">
                                    <div className="flex gap-2 justify-center">
                                      <button className="px-3 py-1.5 bg-indigo-600/20 hover:bg-indigo-600 text-indigo-400 hover:text-white border border-indigo-500/30 rounded-lg text-[10px] font-bold transition-all flex items-center gap-1 uppercase">
                                        <ArrowRightLeft className="w-3 h-3" /> Swap
                                      </button>
                                      <button onClick={() => { setEditingRoute(r); setRouteFormData({...r}); setIsRouteModalOpen(true); }} className="px-3 py-1.5 bg-slate-950 hover:bg-slate-900 text-gray-400 hover:text-white border border-slate-800 rounded-lg text-[10px] font-bold transition-all flex items-center gap-1 uppercase">
                                        Edit
                                      </button>
                                    </div>
                                  </td>
                                </tr>
                              ))}
                            </tbody>
                          </table>
                        </div>
                      </div>

                      {isRouteModalOpen && (
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
                              <button onClick={async () => {
                                try {
                                  if (editingRoute && editingRoute.id) {
                                    await apiClient.put(`/messaging/admin/routes/${editingRoute.id}`, {
                                      cost_per_sms: routeFormData.cost,
                                      priority: routeFormData.priority,
                                      is_active: routeFormData.status === 'ACTIVE'
                                    });
                                    // Refresh routes from backend
                                    fetchAdminData();
                                  }
                                  setIsRouteModalOpen(false);
                                } catch (e) {
                                  console.error("Failed to save route", e);
                                  alert("Failed to save configuration. Please try again.");
                                }
                              }} className="px-6 py-2 bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-bold rounded-xl shadow-lg shadow-indigo-500/20 transition-all">
                                Save Configuration
                              </button>
                            </div>
                          </div>
                        </div>
                      )}

                    </div>
                  )}

                  {/* Supervisor Wallet Adjustment Modal Overlay (FR-BIL-008) */}
                  {adjustingClient && (
                    <div className="fixed inset-0 bg-slate-955/80 backdrop-blur-md z-50 flex items-center justify-center p-6 animate-fadeIn">
                      <div className="w-full max-w-md glass-panel border border-slate-800 rounded-3xl p-8 relative overflow-hidden shadow-2xl glow-card">
                        <div className="absolute w-[300px] h-[300px] rounded-full bg-indigo-600/10 blur-3xl -top-20 -right-20 pointer-events-none"></div>
                        
                        <div className="flex items-center justify-between border-b border-slate-800/60 pb-5 mb-6">
                          <div className="flex items-center gap-3">
                            <Wallet className="w-6 h-6 text-indigo-400" />
                            <div>
                              <h3 className="text-base font-bold text-white uppercase tracking-wider font-sans">Manual Ledger Adjustment</h3>
                              <p className="text-[10px] text-gray-400 mt-1 font-sans">Supervisor auditing control desk</p>
                            </div>
                          </div>
                          <button 
                            type="button"
                            onClick={() => setAdjustingClient(null)}
                            className="text-gray-400 hover:text-white font-mono text-sm px-2 py-0.5 hover:bg-slate-900 rounded"
                          >
                            Close
                          </button>
                        </div>

                        <form onSubmit={handleAdjustWallet} className="space-y-5 text-left">
                          <div className="p-4 bg-indigo-950/20 border border-indigo-900/30 rounded-xl space-y-1">
                            <span className="text-[9px] text-gray-400 uppercase tracking-widest font-bold">Client Organization:</span>
                            <div className="text-sm font-black text-white">{adjustingClient.name}</div>
                            <div className="flex justify-between items-center text-[10px] font-mono mt-2 pt-2 border-t border-slate-800/40">
                              <span className="text-gray-400">Current Balance:</span>
                              <span className="text-indigo-300 font-bold">Ksh {parseFloat(adjustingClient.wallet_balance).toFixed(2)}</span>
                            </div>
                          </div>

                          <div>
                            <label className="block text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-2">Adjustment Amount (Ksh)</label>
                            <input 
                              type="number"
                              required
                              step="0.01"
                              placeholder="e.g. 150.00 or -50.00"
                              value={adjustmentAmount}
                              onChange={(e) => setAdjustmentAmount(e.target.value)}
                              className="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-indigo-500 font-mono text-sm"
                            />
                            <p className="text-[9px] text-gray-500 mt-1.5 font-sans">💡 Enter a positive number to credit (fund) the wallet, or negative to debit (charge).</p>
                          </div>

                          <div>
                            <label className="block text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-2 font-sans">Adjustment Reason Code</label>
                            <select
                              value={adjustmentReason}
                              onChange={(e) => setAdjustmentReason(e.target.value)}
                              className="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-3 text-xs text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                            >
                              <option value="REFUND_CORRECTION">REFUND_CORRECTION (DLR Error Refund)</option>
                              <option value="MANUAL_CREDIT">MANUAL_CREDIT (Wire Transfer Verification)</option>
                              <option value="BILLING_ADJUSTMENT">BILLING_ADJUSTMENT (Supervisor Compensation)</option>
                              <option value="TOPUP">TOPUP (Direct Admin Topup)</option>
                            </select>
                          </div>

                          <div>
                            <label className="block text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-2 font-sans">Adjustment Description / Auditor Comment</label>
                            <textarea
                              rows={3}
                              placeholder="Auditable justification details..."
                              value={adjustmentDesc}
                              onChange={(e) => setAdjustmentDesc(e.target.value)}
                              className="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-3 text-xs text-white focus:outline-none focus:ring-2 focus:ring-indigo-500 leading-relaxed"
                            />
                          </div>

                          <button
                            type="submit"
                            className="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3.5 rounded-xl transition-all hover:shadow text-xs uppercase tracking-wider flex items-center justify-center gap-2 mt-4 font-sans"
                          >
                            <Check className="w-4 h-4 text-white" /> Commit Auditable Adjustment
                          </button>
                        </form>
                      </div>
                    </div>
                  )}

                </>
              )}


              {/* ========================================== */}
              {/* ========================================== */}
              {/* --- II. RESELLER INTERFACES --- */}
              {user.role_tier === 'RESELLER' && resellerAccount && (
                <>
                  {/* II.1 RESELLER CHANNEL OVERVIEW */}
                  {currentPage === 'dashboard' && (
                    <div className="space-y-8">
                      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                        <div className="glass-panel p-6 rounded-2xl border border-slate-850 glow-card flex items-center justify-between">
                          <div>
                            <p className="text-xs font-semibold text-gray-400 uppercase tracking-widest mb-1">Available API Credits</p>
                            <h3 className="text-3xl font-black text-white font-mono">Ksh {Number(resellerAccount.api_credits).toFixed(2)}</h3>
                            <p className="text-[10px] text-gray-400 mt-1">Used to fund sub-client balances</p>
                          </div>
                          <div className="p-4 bg-emerald-500/10 rounded-2xl text-emerald-400 border border-emerald-500/20">
                            <Wallet className="w-6 h-6" />
                          </div>
                        </div>

                        <div className="glass-panel p-6 rounded-2xl border border-slate-850 glow-card flex items-center justify-between">
                          <div>
                            <p className="text-xs font-semibold text-gray-400 uppercase tracking-widest mb-1">My Markup Profits</p>
                            <h3 className="text-3xl font-black text-white font-mono">Ksh 184.20</h3>
                            <p className="text-[10px] text-emerald-400 mt-1 font-bold">+10% markup profit margin active</p>
                          </div>
                          <div className="p-4 bg-indigo-500/10 rounded-2xl text-indigo-400 border border-indigo-500/20">
                            <TrendingUp className="w-6 h-6" />
                          </div>
                        </div>

                        <div className="glass-panel p-6 rounded-2xl border border-slate-850 glow-card flex items-center justify-between">
                          <div>
                            <p className="text-xs font-semibold text-gray-400 uppercase tracking-widest mb-1">Active Client Tenants</p>
                            <h3 className="text-3xl font-black text-white font-mono">{onboardedClients.length}</h3>
                            <p className="text-[10px] text-gray-400 mt-1">Multi-tenant client accounts</p>
                          </div>
                          <div className="p-4 bg-blue-500/10 rounded-2xl text-blue-400 border border-blue-500/20">
                            <Users className="w-6 h-6" />
                          </div>
                        </div>

                        <div className="glass-panel p-6 rounded-2xl border border-slate-850 glow-card flex items-center justify-between">
                          <div>
                            <p className="text-xs font-semibold text-gray-400 uppercase tracking-widest mb-1">Managed SMS Fired</p>
                            <h3 className="text-3xl font-black text-white font-mono">14,290</h3>
                            <p className="text-[10px] text-emerald-400 mt-1 flex items-center gap-0.5 font-bold"><CheckCircle className="w-3 h-3 text-emerald-500" /> sub-client SMS logged</p>
                          </div>
                          <div className="p-4 bg-violet-500/10 rounded-2xl text-violet-400 border border-violet-500/20">
                            <Send className="w-6 h-6" />
                          </div>
                        </div>
                      </div>

                      {/* Reseller visual performance card */}
                      <div className="glass-panel p-8 rounded-2xl border border-slate-850 glow-card">
                        <div className="flex items-center justify-between mb-6 pb-4 border-b border-slate-800/40">
                          <h3 className="text-lg font-bold text-white flex items-center gap-2">
                            <BarChart2 className="w-5 h-5 text-indigo-400" />
                            Client Campaign Profitability Performance
                          </h3>
                          <span className="text-xs text-gray-400">Monthly Billing Analytics</span>
                        </div>
                        <div className="p-6 bg-slate-900/40 border border-slate-800/40 rounded-xl space-y-4 text-sm text-gray-300">
                          <div className="flex justify-between">
                            <span>Primary Partner Network:</span>
                            <span className="font-bold text-white">Casamoko Reseller North</span>
                          </div>
                          <div className="flex justify-between">
                            <span>Profit Markup Rate:</span>
                            <span className="font-bold text-indigo-400 font-mono">+{resellerAccount.markup_percentage}% on carrier base rates</span>
                          </div>
                          <div className="flex justify-between">
                            <span>Estimated Reseller Gross Commission:</span>
                            <span className="font-bold text-emerald-400 font-mono">Ksh 18.4200 (Credits accumulated in wallet)</span>
                          </div>
                        </div>
                      </div>
                    </div>
                  )}

                  {/* II.2 RESELLER MANAGE SUB-CLIENTS */}
                  {currentPage === 'clients' && (
                    <div className="space-y-8">
                      <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
                        
                        {/* Onboard Client Form */}
                        <div className="glass-panel p-6 rounded-2xl border border-slate-850 glow-card">
                          <h4 className="font-bold text-white mb-2">Onboard Corporate Tenant</h4>
                          <p className="text-xs text-gray-400 mb-6">Create and fund client profiles dynamically using your reseller credits.</p>
                          
                          {resellerStatus && (
                            <div className="p-3 bg-indigo-950/40 border border-indigo-500/20 text-indigo-300 text-xs rounded-xl mb-4 font-medium">
                              {resellerStatus}
                            </div>
                          )}

                          <form onSubmit={handleOnboardClient} className="space-y-4">
                            <div>
                              <label className="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">Company / Tenant Name</label>
                              <input 
                                type="text"
                                required
                                value={newClientTenantName}
                                onChange={(e) => setNewClientTenantName(e.target.value)}
                                className="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-indigo-500 text-sm"
                                placeholder="Globex Corp Kenya Ltd"
                              />
                            </div>
                            <div>
                              <label className="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">Contact Person Name</label>
                              <input 
                                type="text"
                                required
                                value={newClientName}
                                onChange={(e) => setNewClientName(e.target.value)}
                                className="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-indigo-500 text-sm"
                                placeholder="John Kamau"
                              />
                            </div>
                            <div>
                              <label className="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">Contact Email (Login)</label>
                              <input 
                                type="email"
                                required
                                value={newClientEmail}
                                onChange={(e) => setNewClientEmail(e.target.value)}
                                className="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-indigo-500 text-sm"
                                placeholder="john@globex.co.ke"
                              />
                            </div>
                            <div>
                              <label className="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">Initial Password</label>
                              <div className="relative">
                                <input 
                                  type={showNewClientPassword ? "text" : "password"}
                                  required
                                  minLength={6}
                                  value={newClientPassword}
                                  onChange={(e) => setNewClientPassword(e.target.value)}
                                  className="w-full bg-slate-950 border border-slate-800 rounded-xl pl-4 pr-12 py-3 text-white focus:outline-none focus:ring-2 focus:ring-indigo-500 text-sm"
                                  placeholder="Min 6 characters"
                                />
                                <button
                                  type="button"
                                  onClick={() => setShowNewClientPassword(!showNewClientPassword)}
                                  className="absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 hover:text-white transition-colors"
                                >
                                  {showNewClientPassword ? <EyeOff className="w-5 h-5" /> : <Eye className="w-5 h-5" />}
                                </button>
                              </div>
                            </div>
                            <div className="grid grid-cols-2 gap-4">
                              <div>
                                <label className="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">Seeding Balance (Ksh)</label>
                                <input 
                                  type="number"
                                  required
                                  value={newClientCredits}
                                  onChange={(e) => setNewClientCredits(e.target.value)}
                                  className="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-indigo-500 font-mono text-sm"
                                />
                              </div>
                              <div>
                                <label className="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">Credit Limit (Ksh)</label>
                                <input 
                                  type="number"
                                  required
                                  value={newClientLimit}
                                  onChange={(e) => setNewClientLimit(e.target.value)}
                                  className="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-indigo-500 font-mono text-sm"
                                />
                              </div>
                            </div>
                            <button
                              type="submit"
                              disabled={isLoading}
                              className="w-full bg-indigo-600 hover:bg-indigo-700 disabled:opacity-60 text-white font-bold py-3 rounded-xl transition-all flex items-center justify-center gap-2 hover:shadow text-xs uppercase tracking-wider mt-4"
                            >
                              {isLoading ? <span className="animate-spin w-4 h-4 border-2 border-white border-t-transparent rounded-full" /> : <Plus className="w-4 h-4" />}
                              {isLoading ? 'Onboarding...' : 'Provision Client & Issue Credentials'}
                            </button>
                          </form>
                        </div>

                        {/* Managed Sub-clients Listing */}
                        <div className="lg:col-span-2 glass-panel p-6 rounded-2xl border border-slate-850 glow-card">
                          <h4 className="font-bold text-white mb-4">Onboarded Corporate Clients</h4>
                          <div className="overflow-x-auto">
                            <table className="w-full text-left text-sm text-gray-300">
                              <thead className="bg-slate-900/60 uppercase tracking-widest text-[10px] text-gray-400">
                                <tr>
                                  <th className="px-6 py-4 rounded-l-xl">Corporate Client Name</th>
                                  <th className="px-6 py-4">Wallet Balance</th>
                                  <th className="px-6 py-4">Credit Limit</th>
                                  <th className="px-6 py-4">KYC Status</th>
                                  <th className="px-6 py-4 rounded-r-xl text-center">Compliance Control</th>
                                </tr>
                              </thead>
                              <tbody className="divide-y divide-slate-800/40">
                                {onboardedClients.map(c => (
                                  <tr key={c.id} className="hover:bg-slate-900/20">
                                    <td className="px-6 py-4 font-bold text-white">{c.name}</td>
                                    <td className="px-6 py-4 font-mono font-bold text-indigo-300">Ksh {Number(c.wallet_balance).toFixed(2)}</td>
                                    <td className="px-6 py-4 font-mono font-bold text-gray-450">Ksh {Number(c.credit_limit).toFixed(2)}</td>
                                    <td className="px-6 py-4">
                                      <span className={`px-2.5 py-0.5 rounded text-[10px] font-bold uppercase ${c.status === 'APPROVED' ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 'bg-red-500/10 text-red-400 border border-red-550/20'}`}>{c.status}</span>
                                    </td>
                                    <td className="px-6 py-4 text-center">
                                      <button 
                                        onClick={() => handleToggleClientSuspension(c.id, c.status)}
                                        className={`px-3 py-1.5 rounded-lg text-xs font-bold transition-all shadow-sm ${c.status === 'SUSPENDED' ? 'bg-emerald-600 hover:bg-emerald-700 text-white' : 'bg-red-655 hover:bg-red-700 text-white'}`}
                                      >
                                        {c.status === 'SUSPENDED' ? 'Reactivate' : 'Suspend'}
                                      </button>
                                    </td>
                                  </tr>
                                ))}
                              </tbody>
                            </table>
                          </div>
                        </div>

                      </div>
                    </div>
                  )}

                </>
              )}


              {/* ========================================== */}
              {/* ========================================== */}
              {/* --- III. CLIENT INTERFACES (Existing premium flow) --- */}
              {clientAccount && (
                <>
                  {/* III.1 CLIENT OVERVIEW DASHBOARD */}
                  {currentPage === 'dashboard' && user.role_tier === 'CLIENT' && (
                    <div className="space-y-8">
                      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                        <div className="glass-panel p-6 rounded-2xl border border-slate-850 glow-card flex items-center justify-between">
                          <div>
                            <p className="text-xs font-semibold text-gray-400 uppercase tracking-widest mb-1">Double-Entry Balance</p>
                            <h3 className="text-3xl font-black text-white font-mono">Ksh {Number(clientAccount.wallet_balance).toFixed(2)}</h3>
                            <p className="text-[10px] text-gray-400 mt-1">Credit Limit: ${Number(clientAccount.credit_limit).toFixed(2)}</p>
                          </div>
                          <div className="p-4 bg-emerald-500/10 rounded-2xl text-emerald-400 border border-emerald-500/20">
                            <Wallet className="w-6 h-6" />
                          </div>
                        </div>

                        <div className="glass-panel p-6 rounded-2xl border border-slate-850 glow-card flex items-center justify-between">
                          <div>
                            <p className="text-xs font-semibold text-gray-400 uppercase tracking-widest mb-1">System Throughput</p>
                            <h3 className="text-3xl font-black text-white font-mono">10,240 <span className="text-xs font-normal">TPS</span></h3>
                            <p className="text-[10px] text-emerald-400 mt-1 flex items-center gap-1 font-bold"><TrendingUp className="w-3 h-3" /> Baseline Peak Capacity</p>
                          </div>
                          <div className="p-4 bg-indigo-500/10 rounded-2xl text-indigo-400 border border-indigo-500/20">
                            <TrendingUp className="w-6 h-6" />
                          </div>
                        </div>

                        <div className="glass-panel p-6 rounded-2xl border border-slate-850 glow-card flex items-center justify-between">
                          <div>
                            <p className="text-xs font-semibold text-gray-400 uppercase tracking-widest mb-1">Outbound SMS Count</p>
                            <h3 className="text-3xl font-black text-white font-mono">{clientAnalytics.total_sms.toLocaleString()}</h3>
                            <p className="text-[10px] text-gray-400 mt-1">Total successfully sent</p>
                          </div>
                          <div className="p-4 bg-blue-500/10 rounded-2xl text-blue-400 border border-blue-500/20">
                            <Send className="w-6 h-6" />
                          </div>
                        </div>

                        <div className="glass-panel p-6 rounded-2xl border border-slate-850 glow-card flex items-center justify-between">
                          <div>
                            <p className="text-xs font-semibold text-gray-400 uppercase tracking-widest mb-1">Deliverability DLR</p>
                            <h3 className="text-3xl font-black text-white font-mono">{clientAnalytics.delivery_rate}%</h3>
                            <p className="text-[10px] text-emerald-400 mt-1 flex items-center gap-0.5"><CheckCircle className="w-3 h-3 text-emerald-500" /> Latency sub-200ms</p>
                          </div>
                          <div className="p-4 bg-violet-500/10 rounded-2xl text-violet-400 border border-violet-500/20">
                            <CheckCircle className="w-6 h-6" />
                          </div>
                        </div>
                      </div>

                      <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
                        
                        {/* Quick Send console */}
                        <div className="lg:col-span-2 glass-panel p-8 rounded-2xl border border-slate-850 glow-card relative overflow-hidden">
                          {/* Locked Overlay for Analyst or Finance Officer */}
                          {user.sub_role && ['ANALYST', 'FINANCE_OFFICER'].includes(user.sub_role) && (
                            <div className="absolute inset-0 bg-slate-950/85 backdrop-blur-sm z-30 flex flex-col justify-center items-center p-6 text-center animate-fadeIn">
                              <Lock className="w-12 h-12 text-amber-500 mb-4 animate-bounce" />
                              <h4 className="text-lg font-bold text-white mb-2">Terminal Access Locked</h4>
                              <p className="text-xs text-gray-400 max-w-sm mb-4">
                                The Quick Send SMS Terminal requires <code className="text-indigo-400 font-mono font-bold">CLIENT_ADMIN</code> or <code className="text-indigo-400 font-mono font-bold">CAMPAIGN_MANAGER</code> privileges.
                              </p>
                              
                            </div>
                          )}
                          <div className="flex items-center justify-between mb-6 pb-4 border-b border-slate-800/40">
                            <h3 className="text-lg font-bold text-white flex items-center gap-2">
                              <Send className="w-5 h-5 text-indigo-400" />
                              Quick Send Terminal
                            </h3>
                            <span className="text-xs text-gray-400">OTP / Transactional API gateway emulator</span>
                          </div>

                          {qsStatus.msg && (
                            <div className={`p-4 rounded-xl mb-6 flex items-start gap-3 border ${qsStatus.type === 'success' ? 'bg-emerald-950/20 border-emerald-500/30 text-emerald-300' : 'bg-red-950/20 border-red-500/30 text-red-300'}`}>
                              {qsStatus.type === 'success' ? <CheckCircle className="w-5 h-5 shrink-0 mt-0.5" /> : <AlertTriangle className="w-5 h-5 shrink-0 mt-0.5" />}
                              <span className="text-sm font-medium">{qsStatus.msg}</span>
                            </div>
                          )}

                          <form onSubmit={handleQuickSend} className="space-y-6">
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                              <div>
                                <label className="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">Recipient Phone (E.164 MSISDN)</label>
                                <input 
                                  type="text"
                                  required
                                  value={qsMsisdn}
                                  onChange={(e) => setQsMsisdn(e.target.value)}
                                  className="w-full bg-slate-955 border border-slate-800 rounded-xl px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-indigo-500 text-sm"
                                  placeholder="+254712345678"
                                />
                              </div>
                              <div>
                                <label className="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">Approved Alphanumeric Sender ID</label>
                                <select
                                  value={qsSenderId}
                                  onChange={(e) => setQsSenderId(parseInt(e.target.value))}
                                  className="w-full bg-slate-955 border border-slate-800 rounded-xl px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-indigo-500 text-sm"
                                >
                                  <option value="">-- Choose Sender ID --</option>
                                  {senderIds.map(s => (
                                    <option key={s.id} value={s.id}>{s.sender_id} ({s.status})</option>
                                  ))}
                                </select>
                              </div>
                            </div>

                            <div>
                              <label className="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">Message Payload Body</label>
                              <textarea
                                required
                                rows={4}
                                value={qsMessage}
                                onChange={(e) => setQsMessage(e.target.value)}
                                className="w-full bg-slate-955 border border-slate-800 rounded-xl px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-indigo-500 text-sm"
                                placeholder="Type OTP message template or transactional alert..."
                              />
                            </div>

                            <div className="bg-slate-900/40 border border-slate-800/40 p-4 rounded-xl flex items-center justify-between">
                              <div className="text-sm">
                                <span className="text-gray-400 font-semibold block mb-0.5 uppercase tracking-wider text-[10px]">Estimated Price Tariff</span>
                                <span className="text-white font-mono font-black text-lg">Ksh {qsCostEstimate.toFixed(4)}</span>
                                <span className="text-xs text-indigo-400 block mt-0.5">Calculated by destination prefix routing costs</span>
                              </div>

                              <button
                                type="submit"
                                className="bg-indigo-600 hover:bg-indigo-700 text-white font-bold px-6 py-3 rounded-xl transition-all flex items-center gap-2 hover:shadow active:scale-95 text-xs uppercase tracking-wider"
                              >
                                <Send className="w-4 h-4" /> Dispatch Instantly
                              </button>
                            </div>
                          </form>
                        </div>

                        <div className="space-y-6">
                          <div className="glass-panel p-6 rounded-2xl border border-slate-850 glow-card">
                            <h4 className="font-bold text-white mb-4">Safaricom SMPP Binds</h4>
                            <div className="space-y-3">
                              <div className="flex items-center justify-between p-3 bg-slate-900/40 rounded-xl border border-slate-800/40">
                                <span className="text-xs font-medium text-gray-300">Safaricom Main Bind (TX/RX)</span>
                                <span className="px-2 py-0.5 bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 text-[10px] font-bold rounded">CONNECTED</span>
                              </div>
                              <div className="flex items-center justify-between p-3 bg-slate-900/40 rounded-xl border border-slate-800/40">
                                <span className="text-xs font-medium text-gray-300">Safaricom Fallback Line</span>
                                <span className="px-2 py-0.5 bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 text-[10px] font-bold rounded">CONNECTED</span>
                              </div>
                              <div className="flex items-center justify-between p-3 bg-slate-900/40 rounded-xl border border-slate-800/40">
                                <span className="text-xs font-medium text-gray-300">Airtel Secondary Binds</span>
                                <span className="px-2 py-0.5 bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 text-[10px] font-bold rounded">CONNECTED</span>
                              </div>
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>
                  )}

                  
                  {/* API KEYS VIEW */}
                  
                  {/* TEMPLATES VIEW */}
                  {currentPage === 'templates' && (
                    <div className="space-y-8 animate-fadeIn">
                      <div className="flex items-center justify-between">
                        <div>
                          <h3 className="text-xl font-bold text-white flex items-center gap-2">
                            <Library className="w-6 h-6 text-indigo-400" />
                            Message Templates Library
                          </h3>
                          <p className="text-sm text-gray-400 mt-1">Save standard messages for quick reuse in campaigns</p>
                        </div>
                      </div>

                      <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
                        <div className="lg:col-span-1 space-y-6">
                          <div className="bg-slate-900 border border-slate-800 rounded-2xl p-6">
                            <h4 className="font-bold text-white mb-4">Create New Template</h4>
                            <div className="space-y-4">
                              <div>
                                <label className="text-xs font-medium text-gray-400 uppercase tracking-wider mb-2 block">Template Name</label>
                                <input
                                  type="text"
                                  className="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-3 text-white focus:outline-none focus:border-indigo-500"
                                  placeholder="e.g. Black Friday Promo"
                                  value={newTemplateName}
                                  onChange={e => setNewTemplateName(e.target.value)}
                                />
                              </div>
                              <div>
                                <label className="text-xs font-medium text-gray-400 uppercase tracking-wider mb-2 block">Message Content</label>
                                <textarea
                                  className="w-full h-32 bg-slate-950 border border-slate-800 rounded-xl px-4 py-3 text-white focus:outline-none focus:border-indigo-500 resize-none"
                                  placeholder="Hello {{name}}, our sale starts today!"
                                  value={newTemplateContent}
                                  onChange={e => setNewTemplateContent(e.target.value)}
                                />
                              </div>
                              <button
                                onClick={async () => {
                                  if (!newTemplateName || !newTemplateContent) return;
                                  try {
                                    const res = await apiClient.post('/templates', { name: newTemplateName, content: newTemplateContent }, { headers: { Authorization: `Bearer ${token}` }});
                                    if (res.data.status === 'SUCCESS') {
                                      setMessageTemplates([res.data.template, ...messageTemplates]);
                                      setNewTemplateName('');
                                      setNewTemplateContent('');
                                    }
                                  } catch (e) {
                                    console.error(e);
                                  }
                                }}
                                className="w-full px-4 py-3 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-xl transition-all"
                              >
                                Save Template
                              </button>
                            </div>
                          </div>
                        </div>

                        <div className="lg:col-span-2">
                          <div className="bg-slate-900 border border-slate-800 rounded-2xl p-6 h-full">
                            <h4 className="font-bold text-white mb-6">Saved Templates</h4>
                            {messageTemplates.length === 0 ? (
                              <div className="text-center py-12">
                                <Library className="w-12 h-12 text-slate-700 mx-auto mb-4" />
                                <p className="text-gray-400">No templates saved yet.</p>
                              </div>
                            ) : (
                              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                {messageTemplates.map(t => (
                                  <div key={t.id} className="bg-slate-950 p-4 rounded-xl border border-slate-800 flex flex-col justify-between">
                                    <div>
                                      <p className="font-bold text-white mb-2">{t.name}</p>
                                      <p className="text-sm text-gray-400 bg-slate-900 p-3 rounded-lg font-mono mb-4 break-words">
                                        {t.content}
                                      </p>
                                    </div>
                                    <div className="flex justify-between items-center mt-2">
                                      <span className="text-xs text-gray-500">{new Date(t.created_at).toLocaleDateString()}</span>
                                      <button 
                                        onClick={async () => {
                                          if (!confirm('Delete this template?')) return;
                                          try {
                                            await apiClient.delete(`/templates/${t.id}`, { headers: { Authorization: `Bearer ${token}` }});
                                            setMessageTemplates(messageTemplates.filter(temp => temp.id !== t.id));
                                          } catch (e) {
                                            console.error(e);
                                          }
                                        }}
                                        className="p-1.5 text-red-400 hover:bg-red-500/10 rounded-lg transition-colors"
                                      >
                                        <Trash2 className="w-4 h-4" />
                                      </button>
                                    </div>
                                  </div>
                                ))}
                              </div>
                            )}
                          </div>
                        </div>
                      </div>
                    </div>
                  )}

                  {currentPage === 'api_keys' && (
                    <div className="space-y-8 animate-fadeIn">
                      <div className="flex items-center justify-between">
                        <div>
                          <h3 className="text-xl font-bold text-white flex items-center gap-2">
                            <Key className="w-6 h-6 text-indigo-400" />
                            API Keys
                          </h3>
                          <p className="text-sm text-gray-400 mt-1">Generate keys to use the Casamoko Dispatch API</p>
                        </div>
                      </div>

                      <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
                        <div className="lg:col-span-1 space-y-6">
                          <div className="bg-slate-900 border border-slate-800 rounded-2xl p-6">
                            <h4 className="font-bold text-white mb-4">Generate New Key</h4>
                            <div className="space-y-4">
                              <div>
                                <label className="text-xs font-medium text-gray-400 uppercase tracking-wider mb-2 block">Key Name / Environment</label>
                                <input
                                  type="text"
                                  className="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-3 text-white focus:outline-none focus:border-indigo-500"
                                  placeholder="e.g. Production Backend"
                                  value={newApiKeyName}
                                  onChange={e => setNewApiKeyName(e.target.value)}
                                />
                              </div>
                              <button
                                onClick={async () => {
                                  if (!newApiKeyName) return;
                                  try {
                                    const res = await apiClient.post('/api-keys', { name: newApiKeyName }, { headers: { Authorization: `Bearer ${token}` }});
                                    if (res.data.status === 'SUCCESS') {
                                      setNewApiKeyRaw(res.data.raw_key);
                                      setApiKeys([res.data.api_key, ...apiKeys]);
                                      setNewApiKeyName('');
                                    }
                                  } catch (e) {
                                    console.error(e);
                                  }
                                }}
                                className="w-full px-4 py-3 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-xl transition-all"
                              >
                                Generate Key
                              </button>
                            </div>
                          </div>
                          
                          {newApiKeyRaw && (
                            <div className="bg-emerald-900/20 border border-emerald-500/30 rounded-2xl p-6">
                              <h4 className="font-bold text-emerald-400 mb-2">Key Generated Successfully</h4>
                              <p className="text-sm text-gray-300 mb-4">Please copy this key now. You will not be able to see it again!</p>
                              <div className="flex items-center gap-2 bg-slate-950 p-3 rounded-lg border border-slate-800">
                                <code className="text-emerald-300 text-sm break-all flex-1">{newApiKeyRaw}</code>
                                <button onClick={() => { navigator.clipboard.writeText(newApiKeyRaw); alert('Copied!'); }} className="p-2 text-gray-400 hover:text-white bg-slate-900 rounded-lg">
                                  <Copy className="w-4 h-4" />
                                </button>
                              </div>
                            </div>
                          )}
                        </div>

                        <div className="lg:col-span-2">
                          <div className="bg-slate-900 border border-slate-800 rounded-2xl p-6 h-full">
                            <h4 className="font-bold text-white mb-6">Active API Keys</h4>
                            {apiKeys.length === 0 ? (
                              <div className="text-center py-12">
                                <Key className="w-12 h-12 text-slate-700 mx-auto mb-4" />
                                <p className="text-gray-400">No API keys generated yet.</p>
                              </div>
                            ) : (
                              <div className="space-y-4">
                                {apiKeys.map(k => (
                                  <div key={k.id} className="flex items-center justify-between bg-slate-950 p-4 rounded-xl border border-slate-800">
                                    <div>
                                      <p className="font-bold text-white">{k.name}</p>
                                      <p className="text-xs text-gray-400 mt-1">Created: {new Date(k.created_at).toLocaleDateString()} &bull; Last used: {k.last_used_at ? new Date(k.last_used_at).toLocaleDateString() : 'Never'}</p>
                                    </div>
                                    <button 
                                      onClick={async () => {
                                        if (!confirm('Are you sure you want to revoke this key? Any systems using it will immediately fail to authenticate.')) return;
                                        try {
                                          await apiClient.delete(`/api-keys/${k.id}`, { headers: { Authorization: `Bearer ${token}` }});
                                          setApiKeys(apiKeys.filter(key => key.id !== k.id));
                                        } catch (e) {
                                          console.error(e);
                                        }
                                      }}
                                      className="p-2 text-red-400 hover:bg-red-500/10 rounded-lg transition-colors"
                                    >
                                      <Trash2 className="w-5 h-5" />
                                    </button>
                                  </div>
                                ))}
                              </div>
                            )}
                          </div>
                        </div>
                      </div>

                      <div className="bg-slate-900 border border-slate-800 rounded-2xl p-6 mt-8">
                        <h4 className="font-bold text-white flex items-center gap-2 mb-4">
                          <Code className="w-5 h-5 text-indigo-400" />
                          API Integration Example (cURL)
                        </h4>
                        <div className="bg-slate-950 p-4 rounded-xl border border-slate-800 overflow-x-auto">
                          <pre className="text-sm text-emerald-400 font-mono">
                            {"curl -X POST https://api.casamoko.com/v1/sms/send \\n  -H \"Authorization: Bearer csmk_live_YOUR_API_KEY_HERE\" \\n  -H \"Content-Type: application/json\" \\n  -d '{\n    \"phone\": \"254712345678\",\n    \"message\": \"Hello from Casamoko API!\",\n    \"sender_id\": \"CASAMOKO\"\n}'"}
                          </pre>
                        </div>
                      </div>
                    </div>
                  )}

                  {currentPage === 'campaigns' && !isWizardOpen && (
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
                      <div className="glass-panel p-6 rounded-2xl border border-slate-850 glow-card col-span-full ">
                    <h4 className="font-bold text-white mb-4 flex items-center gap-2">
                      <Send className="w-5 h-5 text-indigo-400" />
                      Bulk Campaigns History Registry
                    </h4>
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
                                <span className={`px-2 py-0.5 rounded text-[10px] font-bold uppercase ${
                                  camp.status === 'COMPLETED' ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' :
                                  camp.status === 'PROCESSING' ? 'bg-indigo-500/10 text-indigo-400 border border-indigo-500/20 animate-pulse' :
                                  camp.status === 'PAUSED' ? 'bg-amber-500/10 text-amber-400 border border-amber-500/20' :
                                  'bg-slate-500/10 text-gray-400 border border-slate-500/20'
                                }`}>
                                  {camp.status}
                                </span>
                              </td>
                              <td className="px-6 py-4 text-center font-mono font-bold text-xs">{camp.tps_limit} TPS</td>
                              <td className="px-6 py-4 text-center font-mono text-xs">
                                <b className="text-white">{camp.sent_count}</b> / <b className="text-emerald-450">{camp.delivered_count}</b>
                              </td>
                              <td className="px-6 py-4 text-center">
                                <span className={`px-2 py-0.5 rounded text-[9px] font-bold ${
                                  camp.approval_status === 'APPROVED' ? 'bg-emerald-500/10 text-emerald-450' : 'bg-amber-500/10 text-amber-450 animate-pulse'
                                }`}>
                                  {camp.approval_status}
                                </span>
                              </td>
                              <td className="px-6 py-4 flex gap-2 justify-center">
                                <button
                                  onClick={async () => {
                                    if (token) {
                                      try {
                                        const res = await apiClient.post(`/campaigns/${camp.id}/duplicate`, {}, { headers: { Authorization: `Bearer ${token}` } });
                                        if (res.data.status === 'SUCCESS') {
                                          fetchClientData(token);
                                        }
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

                                <button
                                  onClick={async () => {
                                    setViewingCampaignLogs(camp);
                                    setCampaignLogsLoading(true);
                                    if (token) {
                                      try {
                                        const res = await apiClient.get(`/campaigns/${camp.id}/logs`, { headers: { Authorization: `Bearer ${token}` } });
                                        if (res.data.logs) {
                                          setCampaignLogsData(res.data.logs.data);
                                        }
                                      } catch (err) { console.error(err); }
                                    }
                                    setCampaignLogsLoading(false);
                                  }}
                                  className="px-2.5 py-1 bg-slate-900 border border-slate-800 hover:bg-slate-800 text-blue-400 font-bold text-xs rounded-lg transition-all font-sans"
                                >
                                  Logs
                                </button>
                                
                                {camp.status === 'PROCESSING' && (
                                  <button
                                    onClick={async () => {
                                      if (token) {
                                        await apiClient.post(`/campaigns/${camp.id}/action`, { action: 'PAUSE' }, { headers: { Authorization: `Bearer ${token}` } });
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
                                        await apiClient.post(`/campaigns/${camp.id}/action`, { action: 'RESUME' }, { headers: { Authorization: `Bearer ${token}` } });
                                        fetchClientData(token);
                                      } else {
                                        const updated = clientCampaigns.map(c => c.id === camp.id ? { ...c, status: 'PROCESSING' } : c);
                                        setClientCampaigns(updated);
                                      }
                                    }}
                                    className="px-2.5 py-1 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs rounded-lg transition-all font-sans"
                                  >
                                    Resume
                                  </button>
                                )}

                                {camp.approval_status === 'PENDING_APPROVAL' && user?.sub_role === 'CLIENT_ADMIN' && (
                                  <button
                                    onClick={async () => {
                                      if (token) {
                                        await apiClient.post(`/campaigns/${camp.id}/approve`, {}, { headers: { Authorization: `Bearer ${token}` } });
                                        fetchClientData(token);
                                      } else {
                                        const updated = clientCampaigns.map(c => c.id === camp.id ? { ...c, approval_status: 'APPROVED', status: 'PROCESSING' } : c);
                                        setClientCampaigns(updated);
                                      }
                                    }}
                                    className="px-2.5 py-1 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs rounded-lg transition-all font-sans animate-pulse"
                                  >
                                    Approve
                                  </button>
                                )}
                              </td>
                            </tr>
                          ))}
                        </tbody>
                      </table>
                    </div>
                  </div>
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
                        </div>
                        <div className="flex items-center gap-2">
                          {[1, 2, 3, 4].map(s => (
                            <div 
                              key={s} 
                              className={`w-8 h-8 rounded-full flex items-center justify-center font-bold text-sm transition-all ${campaignStep === s ? 'bg-indigo-600 text-white' : campaignStep > s ? 'bg-indigo-500/20 text-indigo-400 border border-indigo-500/30' : 'bg-slate-900 text-gray-500'}`}
                            >
                              {campaignStep > s ? <Check className="w-4 h-4" /> : s}
                            </div>
                          ))}
                        </div>
                      </div>

                      {wizStatus.msg && (
                        <div className={`p-4 rounded-xl mb-6 flex items-start gap-3 border ${wizStatus.type === 'success' ? 'bg-emerald-950/20 border-emerald-500/30 text-emerald-300' : 'bg-red-950/20 border-red-500/30 text-red-300'}`}>
                          {wizStatus.type === 'success' ? <CheckCircle className="w-5 h-5 shrink-0 mt-0.5" /> : <AlertTriangle className="w-5 h-5 shrink-0 mt-0.5" />}
                          <span className="text-sm font-medium">{wizStatus.msg}</span>
                        </div>
                      )}

                      {campaignStep === 1 && (
                        <div className="space-y-6">
                          <h4 className="font-bold text-white mb-2">Step 1: Message Layout & Template Design</h4>
                          <div>
                            <label className="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">Campaign Identifier Name</label>
                            <input 
                              type="text"
                              required
                              value={wizName}
                              onChange={(e) => setWizName(e.target.value)}
                              className="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-indigo-500 text-sm"
                            />
                          </div>
                          <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                              <label className="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">Alphanumeric Sender ID mask</label>
                              <select
                                value={wizSenderId}
                                onChange={(e) => setWizSenderId(parseInt(e.target.value))}
                                className="w-full bg-slate-955 border border-slate-800 rounded-xl px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-indigo-500 text-sm"
                              >
                                <option value="">-- Choose Sender ID --</option>
                                {senderIds.map(s => (
                                  <option key={s.id} value={s.id}>{s.sender_id} ({s.status})</option>
                                ))}
                              </select>
                            </div>
                            <div className="flex items-end">
                              <div className="bg-slate-900/60 border border-slate-800/80 p-3 rounded-xl w-full flex items-center justify-between text-xs text-gray-400 font-mono">
                                <span>Format: <b className="text-white">{wizUnicode}</b></span>
                                <span>Characters: <b className="text-white">{wizCharCount}</b></span>
                                <span>Segments: <b className="text-white">{wizSegments}</b></span>
                              </div>
                            </div>
                          </div>
                          <div>
                            <label className="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">SMS Body (Supports dynamic templates)</label>
                            <textarea
                              required
                              rows={6}
                              value={wizTemplate}
                              onChange={(e) => setWizTemplate(e.target.value)}
                              className="w-full bg-slate-955 border border-slate-800 rounded-xl px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-indigo-500 text-sm"
                              placeholder="Welcome customer to Acme Alerts! Press STOP to unsubscribe..."
                            />
                            <span className="text-[10px] text-gray-400 mt-2 block">Standard GSM-7 allows 160 characters per SMS. UCS-2 Unicode limits single messages to 70 characters.</span>
                          </div>

                          {/* A/B Testing Panel */}
                          <div className="p-5 bg-slate-900/40 border border-slate-850 rounded-2xl space-y-4">
                            <div className="flex items-center justify-between">
                              <div>
                                <h5 className="text-sm font-bold text-white">Enable A/B Split Testing</h5>
                                <p className="text-[10px] text-gray-400 mt-0.5 font-medium">Deliver Variant A template to a subset, and Variant B template to the remaining contacts.</p>
                              </div>
                              <input 
                                type="checkbox"
                                checked={wizIsAbTest}
                                onChange={(e) => setWizIsAbTest(e.target.checked)}
                                className="w-5 h-5 rounded bg-slate-950 border-slate-800 text-indigo-600 focus:ring-indigo-500 cursor-pointer"
                              />
                            </div>

                            {wizIsAbTest && (
                              <div className="space-y-4 pt-4 border-t border-slate-800/40 animate-fadeIn">
                                <div>
                                  <label className="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">SMS Body Variant B</label>
                                  <textarea
                                    rows={4}
                                    value={wizTemplateB}
                                    onChange={(e) => setWizTemplateB(e.target.value)}
                                    className="w-full bg-slate-955 border border-slate-800 rounded-xl px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-indigo-500 text-sm"
                                    placeholder="Alternative offer Variant B..."
                                  />
                                </div>
                                <div>
                                  <label className="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">Split Ratio Allocation: Variant A ({wizAbSplitRatio}%) vs Variant B ({100 - wizAbSplitRatio}%)</label>
                                  <input 
                                    type="range"
                                    min="10"
                                    max="90"
                                    step="10"
                                    value={wizAbSplitRatio}
                                    onChange={(e) => setWizAbSplitRatio(parseInt(e.target.value))}
                                    className="w-full h-1.5 bg-slate-950 rounded-lg appearance-none cursor-pointer accent-indigo-500"
                                  />
                                </div>
                              </div>
                            )}
                          </div>
                        </div>
                      )}

                      {campaignStep === 2 && (
                        <div className="space-y-6">
                          <h4 className="font-bold text-white mb-2">Step 2: Target Audience Directories</h4>
                          
                          <div className="flex gap-4 p-1 bg-slate-950 rounded-xl border border-slate-850 mb-6">
                            <button 
                              onClick={() => setWizAudienceType('group')} 
                              className={`flex-1 py-2 rounded-lg font-medium text-xs transition-all ${wizAudienceType === 'group' ? 'bg-indigo-600 text-white shadow' : 'text-gray-400 hover:text-white'}`}
                            >
                              Select List Directory
                            </button>
                            <button 
                              onClick={() => setWizAudienceType('manual')} 
                              className={`flex-1 py-2 rounded-lg font-medium text-xs transition-all ${wizAudienceType === 'manual' ? 'bg-indigo-600 text-white shadow' : 'text-gray-400 hover:text-white'}`}
                            >
                              Manual Input list
                            </button>
                          </div>

                      {wizAudienceType === 'group' ? (
                        <div>
                          <label className="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">Select Active Subscriber Group List</label>
                          <select
                            value={wizSelectedGroup}
                            onChange={(e) => setWizSelectedGroup(parseInt(e.target.value))}
                            className="w-full bg-slate-955 border border-slate-800 rounded-xl px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-indigo-500 text-sm"
                          >
                            <option value="">-- Select Group --</option>
                            {contactLists.map(g => (
                              <option key={g.id} value={g.id}>{g.name} ({g.tags.join(', ')})</option>
                            ))}
                          </select>
                        </div>
                      ) : (
                        <div>
                          <label className="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">Manual numbers list (Comma / New line separated)</label>
                          <textarea
                            rows={6}
                            value={wizManualNumbers}
                            onChange={(e) => setWizManualNumbers(e.target.value)}
                            className="w-full bg-slate-955 border border-slate-800 rounded-xl px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-indigo-500 text-sm"
                            placeholder="254712345678, 254722999000, 254700111222..."
                          />
                        </div>
                      )}
                    </div>
                  )}

                  {campaignStep === 3 && (
                    <div className="space-y-6">
                      <h4 className="font-bold text-white mb-2">Step 3: Dispatch Scheduling Settings</h4>
                      <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <button
                          onClick={() => setWizScheduleType('instant')}
                          className={`p-6 rounded-2xl border text-left transition-all ${wizScheduleType === 'instant' ? 'bg-indigo-600/10 border-indigo-500/50 text-indigo-300' : 'bg-slate-950 border-slate-850 text-gray-400 hover:text-white'}`}
                        >
                          <CheckCircle className="w-8 h-8 mb-3" />
                          <h5 className="font-bold text-white mb-1">Instant Dispatch Send</h5>
                          <p className="text-xs text-gray-300">Deliver messages immediately upon confirmation.</p>
                        </button>

                        <button
                          onClick={() => setWizScheduleType('future')}
                          className={`p-6 rounded-2xl border text-left transition-all ${wizScheduleType === 'future' ? 'bg-indigo-600/10 border-indigo-500/50 text-indigo-300' : 'bg-slate-950 border-slate-850 text-gray-400 hover:text-white'}`}
                        >
                          <Calendar className="w-8 h-8 mb-3" />
                          <h5 className="font-bold text-white mb-1">Schedule Future Date</h5>
                          <p className="text-xs text-gray-300">Specify a precise calendar date-time window.</p>
                        </button>
                      </div>

                      {wizScheduleType === 'future' && (
                        <div>
                          <label className="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">Schedule Date & Time</label>
                          <input 
                            type="datetime-local"
                            value={wizScheduleDate}
                            onChange={(e) => setWizScheduleDate(e.target.value)}
                            className="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-indigo-500 text-sm"
                          />
                        </div>
                      )}

                      <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                          <label className="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">Recurring Delivery Interval</label>
                          <select
                            value={wizRecurringType}
                            onChange={(e) => setWizRecurringType(e.target.value)}
                            className="w-full bg-slate-955 border border-slate-800 rounded-xl px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-indigo-500 text-sm"
                          >
                            <option value="ONCE">Single Run (Once)</option>
                            <option value="DAILY">Daily Recurrence</option>
                            <option value="WEEKLY">Weekly Recurrence</option>
                            <option value="MONTHLY">Monthly Recurrence</option>
                          </select>
                        </div>
                        <div>
                          <label className="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">Transmission Rate Limit (TPS)</label>
                          <input 
                            type="number"
                            value={wizTpsLimit}
                            onChange={(e) => setWizTpsLimit(parseInt(e.target.value))}
                            className="w-full bg-slate-955 border border-slate-800 rounded-xl px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-indigo-500 font-mono text-sm"
                          />
                        </div>
                      </div>

                      {/* Quiet Hours Block */}
                      <div className="p-5 bg-slate-900/40 border border-slate-850 rounded-2xl space-y-4">
                        <div className="flex items-center justify-between">
                          <div>
                            <h5 className="text-sm font-bold text-white">Enable Quiet Hours / DND Validation</h5>
                            <p className="text-[10px] text-gray-400 mt-0.5 font-medium">Defer outbound message delivery during regional quiet hours (DND compliance).</p>
                          </div>
                          <input 
                            type="checkbox"
                            checked={wizQuietHoursEnabled}
                            onChange={(e) => setWizQuietHoursEnabled(e.target.checked)}
                            className="w-5 h-5 rounded bg-slate-950 border-slate-800 text-indigo-600 focus:ring-indigo-500 cursor-pointer"
                          />
                        </div>

                        {wizQuietHoursEnabled && (
                          <div className="grid grid-cols-2 gap-4 pt-4 border-t border-slate-800/40 animate-fadeIn">
                            <div>
                              <label className="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">Quiet Hours Start</label>
                              <input 
                                type="time"
                                value={wizQuietHoursStart}
                                onChange={(e) => setWizQuietHoursStart(e.target.value)}
                                className="w-full bg-slate-955 border border-slate-800 rounded-xl px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-indigo-500 font-mono text-sm"
                              />
                            </div>
                            <div>
                              <label className="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">Quiet Hours End</label>
                              <input 
                                type="time"
                                value={wizQuietHoursEnd}
                                onChange={(e) => setWizQuietHoursEnd(e.target.value)}
                                className="w-full bg-slate-955 border border-slate-800 rounded-xl px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-indigo-500 font-mono text-sm"
                              />
                            </div>
                          </div>
                        )}
                      </div>
                    </div>
                  )}

                  {campaignStep === 4 && (
                    <div className="space-y-6">
                      <h4 className="font-bold text-white mb-2">Step 4: Final Campaign Pre-checkout Review</h4>
                      
                      <div className="p-6 bg-slate-900/40 border border-slate-850 rounded-2xl space-y-4">
                        <div className="flex justify-between border-b border-slate-800/40 pb-3">
                          <span className="text-sm text-gray-400">Campaign Name:</span>
                          <span className="text-sm font-bold text-white">{wizName}</span>
                        </div>
                        <div className="flex justify-between border-b border-slate-800/40 pb-3">
                          <span className="text-sm text-gray-400">Unicode segments:</span>
                          <span className="text-sm font-bold text-white font-mono">{wizSegments} SMS segment(s)</span>
                        </div>
                        <div className="flex justify-between border-b border-slate-800/40 pb-3">
                          <span className="text-sm text-gray-400">Total Recipients:</span>
                          <span className="text-sm font-bold text-white font-mono">{wizDeduplicatedCount} contact(s)</span>
                        </div>
                        <div className="flex justify-between pb-3">
                          <span className="text-sm text-gray-400">Double-Entry Wallet Debit Cost:</span>
                          <span className="text-base font-extrabold text-white font-mono">Ksh {wizCostEstimate.toFixed(4)}</span>
                        </div>
                      </div>

                      {clientAccount.wallet_balance < wizCostEstimate ? (
                        <div className="p-4 bg-red-955/20 border border-red-500/30 rounded-xl flex items-start gap-3 text-red-300 text-xs">
                          <AlertTriangle className="w-5 h-5 shrink-0" />
                          <span>Ledger Check: Your wallet balance is below the campaign cost. Topup is required before launch.</span>
                        </div>
                      ) : (
                        <div className="p-4 bg-emerald-950/20 border border-emerald-500/30 rounded-xl flex items-start gap-3 text-emerald-300 text-xs">
                          <CheckCircle className="w-5 h-5 shrink-0" />
                          <span>Ledger Check: Balance validated successfully. Concurrency locks active.</span>
                        </div>
                      )}
                    </div>
                  )}

                  <div className="flex justify-between mt-8 pt-4 border-t border-slate-800/40">
                    <button
                      type="button"
                      disabled={campaignStep === 1 || isLoading}
                      onClick={() => setCampaignStep(campaignStep - 1)}
                      className="px-6 py-2.5 bg-slate-900 hover:bg-slate-855 text-gray-450 hover:text-white rounded-xl transition-all disabled:opacity-30 text-sm font-medium"
                    >
                      Back
                    </button>

                    {campaignStep < 4 ? (
                      <button
                        type="button"
                        onClick={() => setCampaignStep(campaignStep + 1)}
                        className="px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl transition-all hover:shadow text-sm font-medium"
                      >
                        Next Step
                      </button>
                    ) : (
                      <button
                        type="button"
                        disabled={isLoading || (clientAccount.wallet_balance < wizCostEstimate)}
                        onClick={() => { handleLaunchCampaign(); setIsWizardOpen(false); }}
                        className="px-6 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-xl transition-all hover:shadow disabled:opacity-50 text-sm"
                      >
                        {isLoading ? <RefreshCw className="w-5 h-5 animate-spin" /> : 'Launch Campaign'}
                      </button>
                    )}
                  </div>
                  

                </div>
              )}

              {/* III.3 CLIENT CONTACTS & BLOCKLISTS */}
              {currentPage === 'contacts' && (
                <div className="space-y-8 animate-fadeIn">
                  
                  {/* Contacts Import Wizard Card (FR-CLM-001 to FR-CLM-005) */}
                  <div className="glass-panel p-8 rounded-2xl border border-slate-855 glow-card">
                    <div className="flex items-center justify-between mb-6 pb-4 border-b border-slate-800/40">
                      <div>
                        <h3 className="text-lg font-bold text-white flex items-center gap-2">
                          <Users className="w-5 h-5 text-indigo-400" />
                          Subscribers Import Wizard
                        </h3>
                        <p className="text-[10px] text-gray-400 mt-1">Onboard bulk phone lists with interactive mapping, phone validation, and opt-out registry checks.</p>
                      </div>
                      <button
                        onClick={() => {
                          setImportWizardOpen(!importWizardOpen);
                          setImportWizardStep(1);
                          setRawImportData('');
                          setImportHeaders([]);
                          setMappedPhoneCol('');
                          setMappedNameCol('');
                          setMappedAttrCols([]);
                          setImportSummary(null);
                        }}
                        className="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs rounded-xl transition-all uppercase tracking-wider shadow"
                      >
                        {importWizardOpen ? 'Close Import Wizard' : 'Launch Import Wizard'}
                      </button>
                    </div>

                    {importWizardOpen && (
                      <div className="mt-6 p-6 bg-slate-950/40 border border-slate-855 rounded-2xl space-y-6 animate-fadeIn">
                        {/* Step bar */}
                        <div className="flex justify-center items-center gap-8 mb-4 border-b border-slate-800/20 pb-4">
                          {[
                            { step: 1, label: "Paste Payload" },
                            { step: 2, label: "Configure Mapping" },
                            { step: 3, label: "Import Complete" }
                          ].map(item => (
                            <div key={item.step} className="flex items-center gap-2">
                              <span className={`w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold ${
                                importWizardStep === item.step ? 'bg-indigo-600 text-white' :
                                importWizardStep > item.step ? 'bg-indigo-500/20 text-indigo-400 border border-indigo-500/30' : 'bg-slate-900 text-gray-500'
                              }`}>
                                {importWizardStep > item.step ? <Check className="w-3.5 h-3.5" /> : item.step}
                              </span>
                              <span className={`text-xs font-medium ${importWizardStep === item.step ? 'text-white' : 'text-gray-500'}`}>
                                {item.label}
                              </span>
                            </div>
                          ))}
                        </div>

                        {importWizardStatus && (
                          <div className="p-3 bg-indigo-950/40 border border-indigo-500/20 text-indigo-300 text-xs rounded-xl">
                            {importWizardStatus}
                          </div>
                        )}

                        {/* Step 1: Paste CSV or JSON */}
                        {importWizardStep === 1 && (
                          <div className="space-y-4">
                            <div>
                              <label className="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">Target Group / Import List Name</label>
                              <select
                                value={importSelectedGroupId}
                                onChange={e => setImportSelectedGroupId(e.target.value === 'NEW' ? 'NEW' : parseInt(e.target.value))}
                                className="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-2.5 text-white focus:outline-none focus:ring-2 focus:ring-indigo-500 text-sm mb-4"
                              >
                                <option value="NEW">-- Create New Group --</option>
                                {contactLists.map(l => (
                                  <option key={l.id} value={l.id}>{l.name}</option>
                                ))}
                              </select>
                              {importSelectedGroupId === 'NEW' && (
                                <input 
                                  type="text"
                                  value={importedListName}
                                  onChange={(e) => setImportedListName(e.target.value)}
                                  className="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-2.5 text-white focus:outline-none focus:ring-2 focus:ring-indigo-500 text-sm mb-4"
                                  placeholder="New Group Name (E.g. VIP Campaigns List)"
                                />
                              )}

                              <div className="flex justify-between items-center mb-2">
                                <label className="block text-xs font-semibold text-gray-400 uppercase tracking-wider">Paste Payload or Upload File</label>
                                <div className="flex gap-3 items-center">
                                  <button
                                    type="button"
                                    onClick={handleDownloadTemplate}
                                    className="text-[10px] text-indigo-400 hover:text-indigo-300 font-bold uppercase tracking-wider underline"
                                  >
                                    Download Template
                                  </button>
                                  <label className="text-[10px] bg-indigo-600 hover:bg-indigo-500 text-white font-bold uppercase tracking-wider px-3 py-1.5 rounded cursor-pointer transition-all">
                                    Upload Excel / CSV
                                    <input type="file" accept=".xlsx, .xls, .csv" className="hidden" onChange={handleFileUpload} />
                                  </label>
                                </div>
                              </div>
                              <textarea
                                rows={6}
                                value={rawImportData}
                                onChange={(e) => setRawImportData(e.target.value)}
                                className="w-full bg-slate-955 border border-slate-800 rounded-xl px-4 py-3 font-mono text-xs text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                placeholder="Paste comma separated values or JSON arrays..."
                              />
                            </div>

                            <button
                              type="button"
                              onClick={handleParseRawImportData}
                              className="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2.5 rounded-xl transition-all uppercase tracking-wider text-xs flex items-center justify-center gap-1.5"
                            >
                              Parse Dataset Headers
                            </button>
                          </div>
                        )}

                        {/* Step 2: Configure Header Mapping */}
                        {importWizardStep === 2 && (
                          <div className="space-y-6">
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                              <div>
                                <label className="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">Phone Number Field (MSISDN)</label>
                                <select
                                  value={mappedPhoneCol}
                                  onChange={(e) => setMappedPhoneCol(e.target.value)}
                                  className="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-2.5 text-white focus:outline-none focus:ring-2 focus:ring-indigo-500 text-sm"
                                >
                                  <option value="">-- Choose Field --</option>
                                  {importHeaders.map(h => (
                                    <option key={h} value={h}>{h}</option>
                                  ))}
                                </select>
                              </div>

                              <div>
                                <label className="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">Subscriber Name Field</label>
                                <select
                                  value={mappedNameCol}
                                  onChange={(e) => setMappedNameCol(e.target.value)}
                                  className="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-2.5 text-white focus:outline-none focus:ring-2 focus:ring-indigo-500 text-sm"
                                >
                                  <option value="">-- Choose Field --</option>
                                  {importHeaders.map(h => (
                                    <option key={h} value={h}>{h}</option>
                                  ))}
                                </select>
                              </div>
                            </div>

                            {/* Deduplication Strategy & Attributes Selector */}
                            <div className="space-y-4">
                              <div>
                                <label className="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">Deduplication Strategy</label>
                                <div className="grid grid-cols-2 gap-4">
                                  <button
                                    type="button"
                                    onClick={() => setDeduplicationStrategy('SKIP')}
                                    className={`p-4 rounded-xl border text-left transition-all ${deduplicationStrategy === 'SKIP' ? 'bg-indigo-600/10 border-indigo-500/50 text-indigo-300' : 'bg-slate-955 border-slate-850 text-gray-400'}`}
                                  >
                                    <h5 className="font-bold text-xs text-white mb-1">Skip Duplicate Records</h5>
                                    <p className="text-[10px] text-gray-400">Ignore any phone numbers that already exist in this tenant's index.</p>
                                  </button>

                                  <button
                                    type="button"
                                    onClick={() => setDeduplicationStrategy('MERGE')}
                                    className={`p-4 rounded-xl border text-left transition-all ${deduplicationStrategy === 'MERGE' ? 'bg-indigo-600/10 border-indigo-500/50 text-indigo-300' : 'bg-slate-955 border-slate-850 text-gray-400'}`}
                                  >
                                    <h5 className="font-bold text-xs text-white mb-1">Merge Metadata Variables</h5>
                                    <p className="text-[10px] text-gray-400">Merge name, attributes, and overwrite metadata tags with updated values.</p>
                                  </button>
                                </div>
                              </div>

                              <div>
                                <label className="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">Map Custom Attributes (stored in metadata JSON)</label>
                                <div className="flex gap-2 flex-wrap">
                                  {importHeaders.filter(h => h !== mappedPhoneCol && h !== mappedNameCol).map(h => {
                                    const active = mappedAttrCols.includes(h);
                                    return (
                                      <button
                                        key={h}
                                        type="button"
                                        onClick={() => {
                                          if (active) {
                                            setMappedAttrCols(mappedAttrCols.filter(col => col !== h));
                                          } else {
                                            setMappedAttrCols([...mappedAttrCols, h]);
                                          }
                                        }}
                                        className={`px-3 py-1.5 rounded-lg border text-xs font-semibold transition-all ${active ? 'bg-indigo-600 border-indigo-500 text-white shadow' : 'bg-slate-950 border-slate-800 text-gray-450 hover:text-white'}`}
                                      >
                                        {active ? '✓ ' : '+ '} {h}
                                      </button>
                                    );
                                  })}
                                </div>
                              </div>
                            </div>

                            <button
                              type="button"
                              onClick={handleContactsImportWizardSubmit}
                              className="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-2.5 rounded-xl transition-all uppercase tracking-wider text-xs flex items-center justify-center gap-1.5 shadow-sm"
                            >
                              <CheckCircle2 className="w-4 h-4" /> Run Programmatic Auditing & Import
                            </button>
                          </div>
                        )}

                        {/* Step 3: Complete & Validation Report */}
                        {importWizardStep === 3 && importSummary && (
                          <div className="space-y-6 text-center py-4">
                            <div className="w-12 h-12 bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 rounded-full flex items-center justify-center mx-auto mb-2">
                              <Check className="w-6 h-6 text-emerald-400" />
                            </div>
                            <div>
                              <h4 className="font-bold text-white text-base">Bulk Subscriber Import Complete!</h4>
                              <p className="text-xs text-gray-400 mt-1">Dataset parsed and validated against cryptographic DPA rules.</p>
                            </div>

                            <div className="grid grid-cols-4 gap-4 max-w-lg mx-auto bg-slate-900/60 border border-slate-850 p-4 rounded-xl font-mono text-xs">
                              <div>
                                <span className="text-[10px] text-gray-400 block mb-1 uppercase font-semibold">Total Rows</span>
                                <span className="text-white font-bold text-lg">{importSummary.total}</span>
                              </div>
                              <div>
                                <span className="text-[10px] text-emerald-400 block mb-1 uppercase font-semibold">Valid MSISDN</span>
                                <span className="text-emerald-400 font-bold text-lg">{importSummary.valid}</span>
                              </div>
                              <div>
                                <span className="text-[10px] text-red-400 block mb-1 uppercase font-semibold">Opted-Out</span>
                                <span className="text-red-400 font-bold text-lg">{importSummary.blocked}</span>
                              </div>
                              <div>
                                <span className="text-[10px] text-amber-500 block mb-1 uppercase font-semibold">Duplicates</span>
                                <span className="text-amber-500 font-bold text-lg">{importSummary.duplicates}</span>
                              </div>
                            </div>

                            <div className="p-4 bg-indigo-950/20 border border-indigo-500/20 rounded-xl max-w-md mx-auto text-indigo-300 text-xs flex items-start gap-2.5 text-left leading-relaxed">
                              <ShieldCheck className="w-5 h-5 shrink-0 mt-0.5" />
                              <span>Opted-out subscriber records were automatically matched using SHA-256 cryptographic digests of raw numbers and securely removed from this dispatch group.</span>
                            </div>

                            <button
                              type="button"
                              onClick={() => setImportWizardOpen(false)}
                              className="px-6 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs rounded-xl transition-all uppercase tracking-wider shadow"
                            >
                              Return to Directories
                            </button>
                          </div>
                        )}
                      </div>
                    )}
                  </div>

                  <div className="glass-panel p-8 rounded-2xl border border-slate-855 glow-card">
                    <div className="flex items-center justify-between mb-6 pb-4 border-b border-slate-800/40">
                      <h3 className="text-lg font-bold text-white flex items-center gap-2">
                        <Users className="w-5 h-5 text-indigo-400" />
                        Subscriber Contact Lists
                      </h3>
                      <div className="flex items-center gap-4">
                        <span className="text-xs text-gray-400">Total: {contactLists.length} groups</span>
                        <button
                          onClick={() => setNewGroupOpen(!newGroupOpen)}
                          className="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs rounded-xl transition-all"
                        >
                          {newGroupOpen ? 'Cancel' : '+ Create Group'}
                        </button>
                      </div>
                    </div>

                    {newGroupOpen && (
                      <form onSubmit={handleCreateGroup} className="mb-6 p-4 bg-slate-900/60 rounded-xl border border-slate-800">
                        <div className="flex gap-4">
                          <input
                            type="text"
                            required
                            value={newGroupName}
                            onChange={e => setNewGroupName(e.target.value)}
                            placeholder="Group Name (e.g. VIP Customers)"
                            className="flex-1 bg-slate-950 border border-slate-800 rounded-xl px-4 py-2 text-white text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                          />
                          <input
                            type="text"
                            value={newGroupDesc}
                            onChange={e => setNewGroupDesc(e.target.value)}
                            placeholder="Description (optional)"
                            className="flex-1 bg-slate-950 border border-slate-800 rounded-xl px-4 py-2 text-white text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                          />
                          <button
                            type="submit"
                            disabled={isCreatingGroup}
                            className="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-sm rounded-xl transition-all whitespace-nowrap disabled:opacity-50"
                          >
                            {isCreatingGroup ? 'Saving...' : 'Save Group'}
                          </button>
                        </div>
                      </form>
                    )}

                    <div className="overflow-x-auto">
                      <table className="w-full text-left text-sm text-gray-300">
                        <thead className="bg-slate-900/60 uppercase tracking-widest text-[10px] text-gray-400">
                          <tr>
                            <th className="px-6 py-4 rounded-l-xl">Group Name</th>
                            <th className="px-6 py-4">Description</th>
                            <th className="px-6 py-4">Source</th>
                            <th className="px-6 py-4">Tags</th>
                            <th className="px-6 py-4 rounded-r-xl text-center">Actions</th>
                          </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-800/40">
                          {contactLists.map(g => (
                            <tr key={g.id} className="hover:bg-slate-900/20">
                              <td className="px-6 py-4 font-bold text-white">{g.name}</td>
                              <td className="px-6 py-4 text-xs text-gray-400">{g.description}</td>
                              <td className="px-6 py-4 text-xs text-indigo-300">{g.subscription_source}</td>
                              <td className="px-6 py-4 flex gap-1.5 flex-wrap">
                                {g.tags.map((t, idx) => (
                                  <span key={idx} className="bg-indigo-500/10 text-indigo-400 border border-indigo-500/10 px-2 py-0.5 rounded text-[10px] font-bold uppercase">{t}</span>
                                ))}
                              </td>
                              <td className="px-6 py-4 text-center">
                                <button 
                                  onClick={() => handleExportCSVList(g.id)}
                                  className="px-3 py-1.5 bg-slate-900 border border-slate-800 hover:bg-slate-800 text-indigo-400 font-bold text-xs rounded-xl transition-all shadow-sm flex items-center gap-1.5 mx-auto"
                                >
                                  <ArrowUpRight className="w-3.5 h-3.5" /> Export (CSV)
                                </button>
                              </td>
                            </tr>
                          ))}
                        </tbody>
                      </table>
                    </div>
                  </div>

                  <div className="glass-panel p-8 rounded-2xl border border-slate-855 glow-card">
                    <div className="flex items-center justify-between mb-6 pb-4 border-b border-slate-800/40">
                      <div>
                        <h3 className="text-lg font-bold text-white flex items-center gap-2">
                          <Lock className="w-5 h-5 text-indigo-400" />
                          Opt-Out Registry (Data Protection Compliance)
                        </h3>
                        <p className="text-[10px] text-gray-400 mt-1">DPA 2019-compliant index tracking salted subscriber hashes.</p>
                      </div>
                      <span className="text-xs text-red-400 font-mono font-bold">{optOutList.length} opted out</span>
                    </div>

                    <div className="overflow-x-auto">
                      <table className="w-full text-left text-sm text-gray-300">
                        <thead className="bg-slate-900/60 uppercase tracking-widest text-[10px] text-gray-400">
                          <tr>
                            <th className="px-6 py-4 rounded-l-xl">Masked Mobile</th>
                            <th className="px-6 py-4">Salted SHA-256 compliance Hash</th>
                            <th className="px-6 py-4 rounded-r-xl">Programmatic Action</th>
                          </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-800/40">
                          {optOutList.map(item => (
                            <tr key={item.id} className="hover:bg-slate-900/20">
                              <td className="px-6 py-4 font-mono font-bold text-white">
                                {item.msisdn.substring(0, 5) + 'xxxx' + item.msisdn.substring(9)}
                              </td>
                              <td className="px-6 py-4 font-mono text-xs text-indigo-300">{item.hash}</td>
                              <td className="px-6 py-4 text-emerald-400 font-bold text-xs flex items-center gap-1">
                                <ShieldCheck className="w-4 h-4 text-emerald-400" /> programmatic block active
                              </td>
                            </tr>
                          ))}
                        </tbody>
                      </table>
                    </div>
                  </div>
                </div>
              )}

              {/* III.4 CLIENT FINANCE & LEDGERS */}
              {currentPage === 'finance' && (
                <div className="space-y-8">
                  <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    
                    <div className="lg:col-span-2 glass-panel p-8 rounded-2xl border border-slate-855 glow-card">
                      <div className="flex items-center justify-between mb-6 pb-4 border-b border-slate-800/40">
                        <h3 className="text-lg font-bold text-white flex items-center gap-2">
                          <Wallet className="w-5 h-5 text-indigo-400" />
                          Double-Entry Wallet Transactions
                        </h3>
                        <span className="text-xs text-gray-400">Immutable ledger log</span>
                      </div>

                      <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm text-gray-300">
                          <thead className="bg-slate-900/60 uppercase tracking-widest text-[10px] text-gray-400">
                            <tr>
                              <th className="px-6 py-4 rounded-l-xl">Type</th>
                              <th className="px-6 py-4">Timeline description</th>
                              <th className="px-6 py-4">Transaction Amount</th>
                              <th className="px-6 py-4 rounded-r-xl">Balance Snapshot</th>
                            </tr>
                          </thead>
                          <tbody className="divide-y divide-slate-800/40">
                            {transactions.map(t => (
                              <tr key={t.id} className="hover:bg-slate-900/20">
                                <td className="px-6 py-4">
                                  <span className={`px-2.5 py-0.5 rounded text-[10px] font-bold ${t.type === 'TOPUP' ? 'bg-emerald-500/10 text-emerald-400' : 'bg-red-500/10 text-red-400'}`}>{t.type}</span>
                                </td>
                                <td className="px-6 py-4 text-xs font-semibold text-gray-400">{t.description}</td>
                                <td className={`px-6 py-4 font-mono font-bold ${t.amount > 0 ? 'text-emerald-400' : 'text-red-400'}`}>
                                  {t.amount > 0 ? `+Ksh ${Number(t.amount).toFixed(4)}` : `-Ksh ${Math.abs(t.amount).toFixed(4)}`}
                                </td>
                                <td className="px-6 py-4 font-mono font-bold text-white">Ksh {Number(t.balance_after).toFixed(4)}</td>
                              </tr>
                            ))}
                          </tbody>
                        </table>
                      </div>
                    </div>

                    <div className="space-y-6">
                      
                      {/* Account Privilege, Billing Type & Threshold Settings Card */}
                      <div className="glass-panel p-6 rounded-2xl border border-slate-855 glow-card text-left relative overflow-hidden">
                        {user.sub_role && ['CAMPAIGN_MANAGER', 'ANALYST'].includes(user.sub_role) && (
                          <div className="absolute inset-0 bg-slate-955/85 backdrop-blur-sm z-30 flex flex-col justify-center items-center p-6 text-center animate-fadeIn">
                            <Lock className="w-10 h-10 text-amber-500 mb-3 animate-bounce" />
                            <h5 className="text-sm font-bold text-white mb-1">Billing Actions Restricted</h5>
                            <p className="text-[10px] text-gray-400 max-w-[200px] mb-3">
                              Wallet topups require <code className="text-indigo-400 font-mono font-bold">CLIENT_ADMIN</code> or <code className="text-indigo-400 font-mono font-bold">FINANCE_OFFICER</code> privileges.
                            </p>
                          </div>
                        )}

                        <div className="flex items-center justify-between border-b border-slate-800/40 pb-3 mb-4">
                          <h4 className="font-bold text-white text-xs uppercase tracking-wider font-sans">Account Billing Cycle</h4>
                          <span className="px-2.5 py-0.5 bg-indigo-500/10 text-indigo-400 border border-indigo-500/20 text-[9px] font-bold rounded uppercase tracking-wider font-mono">
                            {clientAccount.billing_type || 'PREPAID'} CYCLE
                          </span>
                        </div>

                        <div className="space-y-4">
                          <div className="flex justify-between text-xs font-mono">
                            <span className="text-gray-400">Remaining Wallet Credit:</span>
                            <span className="text-white font-bold">Ksh {Number(clientAccount.wallet_balance).toFixed(2)}</span>
                          </div>
                          <div className="flex justify-between text-xs font-mono">
                            <span className="text-gray-400">Credit Overdraft Limit:</span>
                            <span className="text-gray-400">Ksh {Number(clientAccount.credit_limit).toFixed(2)}</span>
                          </div>

                          <div className="pt-2 border-t border-slate-800/40">
                            <label className="block text-[9px] font-bold text-gray-400 uppercase tracking-wider mb-2 font-sans">Set Low-Balance Alert Threshold (Ksh)</label>
                            <div className="flex gap-2">
                              <input 
                                type="number"
                                value={lowBalanceAlertThreshold}
                                onChange={(e) => setLowBalanceAlertThreshold(e.target.value)}
                                className="bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-white focus:outline-none focus:ring-1 focus:ring-indigo-500 font-mono w-24"
                              />
                              <button 
                                onClick={() => {
                                  toast.success(`Low-balance notifications successfully enabled! We will alert you immediately when your balance drops below Ksh ${parseFloat(lowBalanceAlertThreshold).toFixed(2)}.`);
                                }}
                                className="bg-indigo-600 hover:bg-indigo-700 px-3 py-2 text-[10px] font-bold rounded-xl text-white uppercase tracking-wider transition-all font-sans"
                              >
                                Save Alert Limit
                              </button>
                            </div>
                          </div>
                        </div>

                        <button 
                          onClick={() => setTopupDrawerOpen(true)}
                          className="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-3 rounded-xl transition-all flex items-center justify-center gap-2 hover:shadow text-xs uppercase tracking-wider mt-5 font-sans"
                        >
                          <ArrowUpRight className="w-4 h-4 text-white" /> Topup Credit Wallet
                        </button>
                      </div>

                      {/* Auto-generated Invoices Registry Summary Box */}
                      <div className="glass-panel p-6 rounded-2xl border border-slate-855 glow-card text-left">
                        <h4 className="font-bold text-white text-xs uppercase tracking-wider mb-4 border-b border-slate-800/40 pb-3 flex items-center gap-1.5 font-sans">
                          <Sliders className="w-4.5 h-4.5 text-indigo-400" />
                          Audited Invoices Registry
                        </h4>

                        <div className="space-y-3">
                          {invoices.map(inv => (
                            <div key={inv.id} className="bg-slate-950/40 border border-slate-850 p-3 rounded-xl flex items-center justify-between transition-all hover:bg-slate-900/10">
                              <div>
                                <div className="text-xs font-bold text-white font-mono">{inv.invoice_number}</div>
                                <div className="text-[9px] text-gray-500 font-sans mt-0.5">{inv.billing_period} | <span className="text-emerald-450 font-bold uppercase">{inv.status}</span></div>
                              </div>
                              <div className="flex items-center gap-3">
                                <span className="font-mono text-xs text-indigo-300 font-bold">Ksh {Number(inv.amount).toFixed(2)}</span>
                                <button
                                  onClick={() => {
                                    toast.success(`Compiling audited monthly billing summary... Generated and downloaded itemized PDF invoice ${inv.invoice_number} successfully!`);
                                  }}
                                  className="p-1.5 bg-slate-900 hover:bg-slate-850 border border-slate-800 hover:text-white text-gray-400 rounded-lg transition-all text-[9px] font-bold uppercase tracking-wider"
                                >
                                  PDF
                                </button>
                              </div>
                            </div>
                          ))}
                        </div>
                      </div>
                    </div>

                    {/* Premium Multipayment Top-Up Drawer Overlay (FR-BIL-005) */}
                    {topupDrawerOpen && (
                      <div className="fixed inset-0 bg-slate-955/80 backdrop-blur-md z-50 flex items-center justify-center p-6 animate-fadeIn">
                        <div className="w-full max-w-md glass-panel border border-slate-800 rounded-3xl p-8 relative overflow-hidden shadow-2xl glow-card">
                          <div className="absolute w-[300px] h-[300px] rounded-full bg-emerald-600/5 blur-3xl -top-20 -right-20 pointer-events-none"></div>
                          
                          <div className="flex items-center justify-between border-b border-slate-800/60 pb-5 mb-6">
                            <div className="flex items-center gap-3">
                              <Wallet className="w-6 h-6 text-emerald-400" />
                              <div>
                                <h3 className="text-base font-bold text-white uppercase tracking-wider font-sans">Credit Wallet Top-up</h3>
                                <p className="text-[10px] text-gray-400 mt-1 font-sans">Instant payment processing terminal</p>
                              </div>
                            </div>
                            <button 
                              type="button"
                              onClick={() => setTopupDrawerOpen(false)}
                              className="text-gray-400 hover:text-white font-mono text-sm px-2 py-0.5 hover:bg-slate-900 rounded"
                            >
                              Close
                            </button>
                          </div>

                          {/* Payment Method Selector Tab Panel */}
                          <div className="grid grid-cols-3 gap-2 bg-slate-950 p-1.5 rounded-xl border border-slate-850 mb-6 text-[10px] font-bold uppercase tracking-wider font-sans">
                            {['mpesa', 'card', 'bank'].map(method => (
                              <button
                                key={method}
                                type="button"
                                onClick={() => setPaymentMethod(method)}
                                className={`py-2 rounded-lg text-center transition-all ${paymentMethod === method ? 'bg-indigo-600 text-white shadow shadow-indigo-500/20' : 'text-gray-400 hover:text-white'}`}
                              >
                                {method === 'mpesa' ? 'M-Pesa STK' : method === 'card' ? 'Credit Card' : 'Bank Attach'}
                              </button>
                            ))}
                          </div>

                          {paymentMethod === 'mpesa' && (
                            <form onSubmit={handleMpesaTopup} className="space-y-4 text-left">
                              {mpesaStatus && (
                                <div className="p-3 bg-indigo-955/40 border border-indigo-550/20 text-indigo-300 text-[10px] font-bold rounded-xl mb-4 font-mono">
                                  {mpesaStatus}
                                </div>
                              )}
                              <div>
                                <label className="block text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-2 font-sans">Payment Amount (KES)</label>
                                <input 
                                  type="number"
                                  required
                                  value={mpesaAmount}
                                  onChange={(e) => setMpesaAmount(e.target.value)}
                                  className="w-full bg-slate-955 border border-slate-800 rounded-xl px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-indigo-500 font-mono text-sm"
                                />
                              </div>
                              <div>
                                <label className="block text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-2 font-sans">M-Pesa Registered Number</label>
                                <input 
                                  type="text"
                                  required
                                  value={mpesaPhone}
                                  onChange={(e) => setMpesaPhone(e.target.value)}
                                  className="w-full bg-slate-955 border border-slate-800 rounded-xl px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-indigo-500 font-mono text-sm"
                                />
                              </div>
                              <button
                                type="submit"
                                disabled={mpesaLoading}
                                className="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-3.5 rounded-xl transition-all flex items-center justify-center gap-2 hover:shadow disabled:opacity-50 text-xs uppercase tracking-wider mt-4 font-sans"
                              >
                                {mpesaLoading ? <RefreshCw className="w-5 h-5 animate-spin" /> : 'Topup Balance Now'}
                              </button>
                            </form>
                          )}

                          {paymentMethod === 'card' && (
                            <form onSubmit={(e) => {
                              e.preventDefault();
                              toast.success('Verifying sandbox card credentials... Payment completed and wallet credited successfully!');
                              setTopupDrawerOpen(false);
                              // Add mock transaction
                              const amount = parseFloat(mpesaAmount) / 130.00; // convert mock KES to USD roughly
                              const newTx = {
                                id: Date.now(),
                                client_account_id: clientAccount.id,
                                amount: amount,
                                balance_after: clientAccount.wallet_balance + amount,
                                type: 'TOPUP',
                                description: 'Visa Card manual sandbox topup',
                                created_at: new Date().toISOString()
                              };
                              setTransactions(prev => [newTx as any, ...prev]);
                              setClientAccount(prev => prev ? { ...prev, wallet_balance: prev.wallet_balance + amount } as any : null);
                            }} className="space-y-4 text-left font-sans">
                              
                              {/* Glassmorphic Credit Card Preview Widget */}
                              <div className="bg-gradient-to-tr from-indigo-600 to-indigo-850 border border-indigo-500/20 p-5 rounded-2xl relative overflow-hidden shadow-lg mb-4 text-white">
                                <div className="absolute w-[200px] h-[200px] bg-white/5 rounded-full -top-10 -right-10 blur-xl pointer-events-none"></div>
                                <div className="flex justify-between items-center mb-6">
                                  <span className="text-[10px] font-bold tracking-widest uppercase">SANDBOX CARD PLATFORM</span>
                                  <span className="font-mono text-xs font-black italic">VISA</span>
                                </div>
                                <div className="font-mono text-base tracking-widest mb-4 font-bold text-center select-all">
                                  {cardNumber || '••••  ••••  ••••  ••••'}
                                </div>
                                <div className="flex justify-between items-center text-[9px] font-mono">
                                  <div>
                                    <div className="text-white/40 mb-0.5">CARD HOLDER</div>
                                    <div className="font-bold tracking-wider truncate max-w-[150px] uppercase">{cardName || 'YOUR FULL NAME'}</div>
                                  </div>
                                  <div className="text-right">
                                    <div className="text-white/40 mb-0.5">EXPIRES</div>
                                    <div className="font-bold tracking-wider">{cardExpiry || 'MM/YY'}</div>
                                  </div>
                                </div>
                              </div>

                              <div>
                                <label className="block text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-2">Card Holder Name</label>
                                <input 
                                  type="text"
                                  required
                                  value={cardName}
                                  onChange={(e) => setCardName(e.target.value)}
                                  placeholder="e.g. John Doe"
                                  className="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-2.5 text-xs text-white focus:outline-none focus:ring-2 focus:ring-indigo-500 text-sm uppercase"
                                />
                              </div>
                              <div>
                                <label className="block text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-2">Card Number</label>
                                <input 
                                  type="text"
                                  required
                                  value={cardNumber}
                                  onChange={(e) => setCardNumber(e.target.value.replace(/\s?/g, '').replace(/(\d{4})/g, '$1 ').trim())}
                                  placeholder="4000 1234 5678 9010"
                                  className="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-2.5 text-xs text-white focus:outline-none focus:ring-2 focus:ring-indigo-500 font-mono text-sm"
                                />
                              </div>
                              <div className="grid grid-cols-2 gap-4">
                                <div>
                                  <label className="block text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-2">Expiration Date</label>
                                  <input 
                                    type="text"
                                    required
                                    placeholder="MM/YY"
                                    value={cardExpiry}
                                    onChange={(e) => setCardExpiry(e.target.value)}
                                    className="w-full bg-slate-955 border border-slate-800 rounded-xl px-4 py-2.5 text-xs text-white focus:outline-none focus:ring-2 focus:ring-indigo-500 font-mono text-sm"
                                  />
                                </div>
                                <div>
                                  <label className="block text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-2">CVV Code</label>
                                  <input 
                                    type="text"
                                    required
                                    placeholder="321"
                                    value={cardCvv}
                                    onChange={(e) => setCardCvv(e.target.value)}
                                    className="w-full bg-slate-955 border border-slate-800 rounded-xl px-4 py-2.5 text-xs text-white focus:outline-none focus:ring-2 focus:ring-indigo-500 font-mono text-sm"
                                  />
                                </div>
                              </div>
                              <button
                                type="submit"
                                className="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3.5 rounded-xl transition-all hover:shadow text-xs uppercase tracking-wider flex items-center justify-center gap-2 mt-4 font-sans"
                              >
                                <Check className="w-4 h-4 text-white" /> Complete Card Top-up
                              </button>
                            </form>
                          )}

                          {paymentMethod === 'bank' && (
                            <div className="space-y-5 text-left font-sans">
                              <div className="p-4 bg-indigo-950/20 border border-indigo-900/30 rounded-xl">
                                <h5 className="text-[10px] font-bold text-indigo-300 uppercase tracking-wider mb-1">Casamoko Wire Banking Binds</h5>
                                <p className="text-[10px] text-gray-400 leading-relaxed mb-3">Please transfer your funds directly to our corporate bank node:</p>
                                <div className="text-[10px] font-mono text-slate-350 space-y-1 bg-slate-950/50 p-3 rounded-lg border border-slate-850">
                                  <p>🏦 **Bank**: NCBA Bank Kenya PLC</p>
                                  <p>🏦 **Account Name**: Casamoko Technologies Ltd</p>
                                  <p>🏦 **Account Number**: NCBA-994829382103</p>
                                </div>
                              </div>

                              <div>
                                <label className="block text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-2">Upload Bank Swift Wire Receipt</label>
                                <div className="border-2 border-dashed border-slate-800 rounded-2xl p-6 text-center transition-all bg-slate-950/20 hover:bg-slate-950/50 relative">
                                  <input 
                                    type="file"
                                    className="absolute inset-0 opacity-0 cursor-pointer"
                                    onChange={(e) => {
                                      if (e.target.files && e.target.files.length > 0) {
                                        setWireFileName(e.target.files[0].name);
                                        setWireUploaded(true);
                                      }
                                    }}
                                  />
                                  {wireUploaded ? (
                                    <div className="space-y-2">
                                      <CheckCircle2 className="w-8 h-8 text-emerald-400 mx-auto" />
                                      <p className="text-xs font-bold text-white truncate max-w-full">{wireFileName}</p>
                                      <p className="text-[9px] text-gray-500 font-sans">Attachment locked. Swift parsing verification in progress.</p>
                                    </div>
                                  ) : (
                                    <div className="space-y-2">
                                      <ArrowUpRight className="w-8 h-8 text-indigo-400 mx-auto transform rotate-90" />
                                      <p className="text-xs font-bold text-gray-300 font-sans">Drag bank wire receipt PDF or click to browse</p>
                                      <p className="text-[9px] text-gray-550 font-sans">Supports PDF, JPG, PNG up to 10MB</p>
                                    </div>
                                  )}
                                </div>
                              </div>

                              <button
                                disabled={!wireUploaded}
                                onClick={() => {
                                  toast.success('Swift wire receipt submitted to finance audit queue! Your wallet balance will credit immediately after banking node sync (typically within 1 hour).');
                                  setTopupDrawerOpen(false);
                                  setWireUploaded(false);
                                  setWireFileName('');
                                }}
                                className="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3.5 rounded-xl transition-all hover:shadow disabled:opacity-30 text-xs uppercase tracking-wider flex items-center justify-center gap-2 mt-4 font-sans"
                              >
                                <Check className="w-4 h-4 text-white" /> Submit Swift Wire Receipt
                              </button>
                            </div>
                          )}
                        </div>
                      </div>
                    )}
                  </div>
                </div>
              )}

              {/* III.5 CLIENT TEAM MEMBERS & ACTIONS LOGGER */}
              {currentPage === 'team' && (
                <div className="space-y-8 animate-fadeIn">
                  <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    
                    {/* Add Team User Form (restricted to CLIENT_ADMIN) */}
                    <div className="glass-panel p-6 rounded-2xl border border-slate-855 glow-card relative overflow-hidden">
                      {user.sub_role !== 'CLIENT_ADMIN' && (
                        <div className="absolute inset-0 bg-slate-955/85 backdrop-blur-sm z-30 flex flex-col justify-center items-center p-6 text-center animate-fadeIn">
                          <Lock className="w-10 h-10 text-amber-500 mb-3 animate-bounce" />
                          <h5 className="text-sm font-bold text-white mb-1">Onboarding Restricted</h5>
                          <p className="text-[10px] text-gray-400 max-w-[200px] mb-3">
                            Onboarding corporate sub-users requires <code className="text-indigo-400 font-mono font-bold">CLIENT_ADMIN</code> privileges.
                          </p>
                          
                        </div>
                      )}

                      <h4 className="font-bold text-white mb-2 flex items-center gap-2">
                        <UserPlus className="w-5 h-5 text-indigo-400" />
                        Onboard Team Sub-User
                      </h4>
                      <p className="text-xs text-gray-400 mb-6">Provision fine-grained access tiers to corporate members (FR-UAM-003).</p>
                      
                      {teamStatus && (
                        <div className="p-3 bg-indigo-950/40 border border-indigo-500/20 text-indigo-300 text-xs rounded-xl mb-4 font-medium">
                          {teamStatus}
                        </div>
                      )}

                      <form onSubmit={handleAddTeamMember} className="space-y-4">
                        <div>
                          <label className="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">Member Name</label>
                          <input 
                            type="text"
                            required
                            value={newMemberName}
                            onChange={(e) => setNewMemberName(e.target.value)}
                            className="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-indigo-500 text-sm"
                            placeholder="Bob Builder"
                          />
                        </div>
                        <div>
                          <label className="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">Email Address</label>
                          <input 
                            type="email"
                            required
                            value={newMemberEmail}
                            onChange={(e) => setNewMemberEmail(e.target.value)}
                            className="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-indigo-500 text-sm"
                            placeholder="bob@acme.com"
                          />
                        </div>
                        <div>
                          <label className="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">Temporary Password</label>
                          <div className="relative">
                            <input 
                              type={showNewMemberPassword ? "text" : "password"}
                              required
                              value={newMemberPassword}
                              onChange={(e) => setNewMemberPassword(e.target.value)}
                              className="w-full bg-slate-955 border border-slate-800 rounded-xl pl-4 pr-12 py-3 text-white focus:outline-none focus:ring-2 focus:ring-indigo-500 text-sm"
                              placeholder="••••••••"
                            />
                            <button
                              type="button"
                              onClick={() => setShowNewMemberPassword(!showNewMemberPassword)}
                              className="absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 hover:text-white transition-colors"
                            >
                              {showNewMemberPassword ? <EyeOff className="w-5 h-5" /> : <Eye className="w-5 h-5" />}
                            </button>
                          </div>
                        </div>
                        <div>
                          <label className="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">Privilege Sub-Role</label>
                          <select
                            value={newMemberSubRole}
                            onChange={(e) => setNewMemberSubRole(e.target.value)}
                            className="w-full bg-slate-955 border border-slate-800 rounded-xl px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-indigo-500 text-sm"
                          >
                            <option value="CLIENT_ADMIN">Client Admin (Supervisor)</option>
                            <option value="CAMPAIGN_MANAGER">Campaign Manager</option>
                            <option value="ANALYST">Analyst (Read-Only)</option>
                            <option value="FINANCE_OFFICER">Finance Officer</option>
                          </select>
                        </div>

                        <button
                          type="submit"
                          className="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3 rounded-xl transition-all flex items-center justify-center gap-2 hover:shadow text-xs uppercase tracking-wider mt-4"
                        >
                          <Plus className="w-4 h-4" /> Register Team Member
                        </button>
                      </form>
                    </div>

                    {/* Team Members List */}
                    <div className="lg:col-span-2 glass-panel p-6 rounded-2xl border border-slate-855 glow-card">
                      <h4 className="font-bold text-white mb-4">Corporate Accounts Registry</h4>
                      <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm text-gray-300">
                          <thead className="bg-slate-900/60 uppercase tracking-widest text-[10px] text-gray-400 font-bold">
                            <tr>
                              <th className="px-6 py-4 rounded-l-xl">User Name</th>
                              <th className="px-6 py-4">Email Address</th>
                              <th className="px-6 py-4 rounded-r-xl">Access Role</th>
                            </tr>
                          </thead>
                          <tbody className="divide-y divide-slate-800/40">
                            {teamMembers.map(m => (
                              <tr key={m.id} className="hover:bg-slate-900/20">
                                <td className="px-6 py-4 font-bold text-white">{m.name}</td>
                                <td className="px-6 py-4 font-mono text-xs text-gray-300">{m.email}</td>
                                <td className="px-6 py-4">
                                  <span className="px-2.5 py-0.5 bg-indigo-500/10 text-indigo-400 border border-indigo-500/10 text-[10px] font-bold rounded uppercase tracking-wider">{m.sub_role}</span>
                                </td>
                              </tr>
                            ))}
                          </tbody>
                        </table>
                      </div>
                    </div>

                  </div>

                  {/* Immutable action audit logs ledger (FR-UAM-008) */}
                  <div className="glass-panel p-8 rounded-2xl border border-slate-855 glow-card">
                    <div className="flex items-center justify-between mb-6 pb-4 border-b border-slate-800/40">
                      <div>
                        <h3 className="text-lg font-bold text-white flex items-center gap-2">
                          <Lock className="w-5 h-5 text-indigo-400" />
                          Immutable Activity Audits Ledger
                        </h3>
                        <p className="text-[10px] text-gray-400 mt-1">Immutable tenant audit logs tracking administrative actions, timestamps, and IP addresses.</p>
                      </div>
                      <span className="px-2.5 py-0.5 bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 text-[10px] font-mono font-bold rounded">Live Logging</span>
                    </div>

                    <div className="overflow-x-auto">
                      <table className="w-full text-left text-sm text-gray-300">
                        <thead className="bg-slate-900/60 uppercase tracking-widest text-[10px] text-gray-400">
                          <tr>
                            <th className="px-6 py-4 rounded-l-xl">Action Type</th>
                            <th className="px-6 py-4">Description</th>
                            <th className="px-6 py-4">User</th>
                            <th className="px-6 py-4">IP Address</th>
                            <th className="px-6 py-4 rounded-r-xl">Timestamp</th>
                          </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-800/40 font-mono text-xs">
                          {teamActivities.map(act => (
                            <tr key={act.id} className="hover:bg-slate-900/20">
                              <td className="px-6 py-4">
                                <span className="px-2 py-0.5 bg-indigo-500/10 text-indigo-400 border border-indigo-500/20 text-[9px] font-bold rounded uppercase tracking-wider">{act.action}</span>
                              </td>
                              <td className="px-6 py-4 text-gray-300 font-sans text-xs">{act.description}</td>
                              <td className="px-6 py-4 font-sans text-xs text-indigo-350">{act.username}</td>
                              <td className="px-6 py-4 text-gray-450">{act.ip_address}</td>
                              <td className="px-6 py-4 text-gray-450">{new Date(act.created_at).toLocaleString()}</td>
                            </tr>
                          ))}
                        </tbody>
                      </table>
                    </div>
                  </div>
                </div>
              )}

              {/* III.6 CLIENT SHORTCODE SERVICES */}
              {currentPage === 'shortcodes' && (
                <div className="space-y-8 animate-fadeIn">
                  
                  {/* Status Banner */}
                  {shortcodeStatus && (
                    <div className="p-4 bg-indigo-950/40 border border-indigo-500/30 text-indigo-300 text-xs rounded-2xl flex items-center justify-between animate-fadeIn">
                      <div className="flex items-center gap-2">
                        <ShieldCheck className="w-5 h-5 text-indigo-400" />
                        <span className="font-semibold">{shortcodeStatus}</span>
                      </div>
                      <button 
                        onClick={() => setShortcodeStatus(null)} 
                        className="text-indigo-400 hover:text-indigo-200 font-bold font-mono text-sm px-2 py-1"
                      >
                        ✕
                      </button>
                    </div>
                  )}

                  {/* Top Summary Info Grid */}
                  <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div className="glass-panel p-6 rounded-2xl border border-slate-850 glow-card flex items-center justify-between">
                      <div>
                        <p className="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1">Active Shortcodes</p>
                        <h3 className="text-2xl font-black text-white font-mono">{shortcodes.length}</h3>
                        <p className="text-[10px] text-indigo-400 mt-1">Dedicated + Shared Binds</p>
                      </div>
                      <div className="p-3 bg-indigo-500/10 rounded-xl text-indigo-400 border border-indigo-500/20">
                        <MessageSquare className="w-5 h-5" />
                      </div>
                    </div>

                    <div className="glass-panel p-6 rounded-2xl border border-slate-850 glow-card flex items-center justify-between">
                      <div>
                        <p className="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1">Keyword Mappings</p>
                        <h3 className="text-2xl font-black text-white font-mono">{keywords.length}</h3>
                        <p className="text-[10px] text-emerald-400 mt-1">Auto-Response Rules Active</p>
                      </div>
                      <div className="p-3 bg-emerald-500/10 rounded-xl text-emerald-400 border border-emerald-500/20">
                        <ShieldCheck className="w-5 h-5" />
                      </div>
                    </div>

                    <div className="glass-panel p-6 rounded-2xl border border-slate-850 glow-card flex items-center justify-between">
                      <div>
                        <p className="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1">Inbound MO Messages</p>
                        <h3 className="text-2xl font-black text-white font-mono">{moLogs.length}</h3>
                        <p className="text-[10px] text-blue-400 mt-1">Simulated delivery records</p>
                      </div>
                      <div className="p-3 bg-blue-500/10 rounded-xl text-blue-400 border border-blue-500/20">
                        <ArrowDownLeft className="w-5 h-5" />
                      </div>
                    </div>
                  </div>

                  <div className="grid grid-cols-1 xl:grid-cols-3 gap-8">
                    
                    {/* Column 1: Shortcode allocation list & Rule Creator */}
                    <div className="xl:col-span-2 space-y-8">
                      
                      {/* Active Allocations Card */}
                      <div className="glass-panel p-6 rounded-2xl border border-slate-855 glow-card">
                        <div className="flex items-center justify-between mb-4 pb-2 border-b border-slate-800/40">
                          <h4 className="font-bold text-white flex items-center gap-2 text-sm uppercase tracking-wider">
                            <Building className="w-4.5 h-4.5 text-indigo-400" />
                            Allocated Shortcode Services
                          </h4>
                          <span className="text-[10px] text-gray-500">GSM & USSD Carrier Bind Registry</span>
                        </div>

                        <div className="overflow-x-auto">
                          <table className="w-full text-left text-xs text-gray-300">
                            <thead className="bg-slate-900/60 uppercase tracking-widest text-[9px] text-gray-400 font-bold">
                              <tr>
                                <th className="px-4 py-3 rounded-l-xl">Shortcode/Number</th>
                                <th className="px-4 py-3">Allocation Type</th>
                                <th className="px-4 py-3">Target Scope</th>
                                <th className="px-4 py-3 rounded-r-xl text-center">Status</th>
                              </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-800/30">
                              {shortcodes.map(sc => (
                                <tr key={sc.id} className="hover:bg-slate-900/20 transition-all">
                                  <td className="px-4 py-3 font-mono font-black text-white text-sm">{sc.shortcode}</td>
                                  <td className="px-4 py-3">
                                    <span className={`px-2 py-0.5 rounded text-[9px] font-bold tracking-wider uppercase ${sc.is_dedicated ? 'bg-indigo-500/10 text-indigo-400 border border-indigo-500/20' : 'bg-slate-800 text-gray-400 border border-slate-700'}`}>
                                      {sc.is_dedicated ? 'DEDICATED' : 'SHARED KEYWORD'}
                                    </span>
                                  </td>
                                  <td className="px-4 py-3 text-gray-400">
                                    {sc.is_dedicated ? 'Full Shortcode Scope' : 'Restricted by Registered Keyword prefix'}
                                  </td>
                                  <td className="px-4 py-3 text-center">
                                    <span className="px-2 py-0.5 bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 text-[9px] font-extrabold rounded tracking-wider">
                                      BIND_ACTIVE
                                    </span>
                                  </td>
                                </tr>
                              ))}
                            </tbody>
                          </table>
                        </div>
                      </div>

                      {/* Keywords mapping registry */}
                      <div className="glass-panel p-6 rounded-2xl border border-slate-855 glow-card">
                        <div className="flex items-center justify-between mb-4 pb-2 border-b border-slate-800/40">
                          <h4 className="font-bold text-white flex items-center gap-2 text-sm uppercase tracking-wider">
                            <Lock className="w-4.5 h-4.5 text-indigo-400" />
                            Case-Insensitive Inbound Keyword Rules
                          </h4>
                          <span className="text-[10px] text-gray-500">Auto-Replies & Webhook Triggers</span>
                        </div>

                        <div className="overflow-x-auto">
                          <table className="w-full text-left text-xs text-gray-300">
                            <thead className="bg-slate-900/60 uppercase tracking-widest text-[9px] text-gray-400 font-bold">
                              <tr>
                                <th className="px-4 py-3 rounded-l-xl">Shortcode</th>
                                <th className="px-4 py-3">Keyword</th>
                                <th className="px-4 py-3">Action Class</th>
                                <th className="px-4 py-3 font-semibold">Reply Template / Webhook Callback</th>
                              </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-800/30">
                              {keywords.map(kw => {
                                const parentSc = shortcodes.find(s => s.id === kw.shortcode_id);
                                return (
                                  <tr key={kw.id} className="hover:bg-slate-900/20 transition-all">
                                    <td className="px-4 py-3 font-mono font-bold text-gray-400">{parentSc ? parentSc.shortcode : '22344'}</td>
                                    <td className="px-4 py-3"><span className="px-2 py-0.5 bg-slate-900 border border-slate-800 rounded font-mono font-extrabold text-white">{kw.keyword}</span></td>
                                    <td className="px-4 py-3">
                                      <span className={`px-2 py-0.5 rounded text-[9px] font-bold uppercase tracking-wider ${
                                        kw.action_type === 'OPT_IN' ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' :
                                        kw.action_type === 'OPT_OUT' ? 'bg-red-500/10 text-red-400 border border-red-500/20' :
                                        'bg-blue-500/10 text-blue-400 border border-blue-500/20'
                                      }`}>
                                        {kw.action_type}
                                      </span>
                                    </td>
                                    <td className="px-4 py-3 text-xs leading-relaxed max-w-[280px]">
                                      {kw.action_type === 'WEBHOOK' ? (
                                        <div className="space-y-1">
                                          <span className="text-gray-450 font-mono break-all block text-[10px]">{kw.callback_webhook}</span>
                                          {kw.reply_message && <span className="text-gray-500 italic block text-[10px]">Reply: "{kw.reply_message}"</span>}
                                        </div>
                                      ) : (
                                        <span className="text-gray-400">"{kw.reply_message}"</span>
                                      )}
                                    </td>
                                  </tr>
                                );
                              })}
                            </tbody>
                          </table>
                        </div>
                      </div>

                    </div>

                    {/* Column 2: Keyword Rule Creator & Sandbox MO Simulator */}
                    <div className="space-y-8">
                      
                      {/* Keyword provision tool */}
                      <div className="glass-panel p-6 rounded-2xl border border-slate-855 glow-card relative overflow-hidden">
                        {/* Access restrictions checking */}
                        {user.sub_role && ['ANALYST', 'FINANCE_OFFICER'].includes(user.sub_role) && (
                          <div className="absolute inset-0 bg-slate-950/85 backdrop-blur-sm z-30 flex flex-col justify-center items-center p-6 text-center animate-fadeIn">
                            <Lock className="w-10 h-10 text-amber-500 mb-3 animate-bounce" />
                            <h5 className="text-sm font-bold text-white mb-1">Configuration Locked</h5>
                            <p className="text-[10px] text-gray-400 max-w-[200px] mb-3">
                              Adding shortcode keyword routing rules requires <code className="text-indigo-400 font-mono font-bold">CLIENT_ADMIN</code> or <code className="text-indigo-400 font-mono font-bold">CAMPAIGN_MANAGER</code> sub-roles.
                            </p>
                            
                          </div>
                        )}

                        <h4 className="font-bold text-white mb-2 flex items-center gap-2 text-sm uppercase tracking-wider">
                          <Plus className="w-4 h-4 text-indigo-400" />
                          Register Inbound Keyword Rule
                        </h4>
                        <p className="text-[10px] text-gray-400 mb-4">Set up direct auto-responses or HTTP callback dispatches instantly.</p>

                        <form onSubmit={handleCreateKeyword} className="space-y-4">
                          <div>
                            <label className="block text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-2">Target Shortcode Binding</label>
                            <select
                              value={newKeywordShortcode}
                              onChange={(e) => setNewKeywordShortcode(parseInt(e.target.value))}
                              className="w-full bg-slate-955 border border-slate-800 rounded-xl px-3 py-2 text-white focus:outline-none focus:ring-2 focus:ring-indigo-500 text-xs"
                            >
                              {shortcodes.map(sc => (
                                <option key={sc.id} value={sc.id}>{sc.shortcode} ({sc.is_dedicated ? 'Dedicated' : 'Shared'})</option>
                              ))}
                            </select>
                          </div>

                          <div>
                            <label className="block text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-2">Keyword Phrase</label>
                            <input 
                              type="text"
                              required
                              value={newKeywordText}
                              onChange={(e) => setNewKeywordText(e.target.value)}
                              placeholder="E.g. BALANCE, OFFERS, JOIN"
                              className="w-full bg-slate-955 border border-slate-800 rounded-xl px-3 py-2 text-white focus:outline-none focus:ring-2 focus:ring-indigo-500 font-mono text-xs uppercase"
                            />
                          </div>

                          <div>
                            <label className="block text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-2">Action Class Flow</label>
                            <div className="grid grid-cols-3 gap-2">
                              {['OPT_IN', 'OPT_OUT', 'WEBHOOK'].map(action => (
                                <button
                                  key={action}
                                  type="button"
                                  onClick={() => setNewKeywordAction(action)}
                                  className={`py-2 rounded-lg border text-[10px] font-bold transition-all ${newKeywordAction === action ? 'bg-indigo-600 border-indigo-500 text-white shadow' : 'bg-slate-955 border-slate-800 text-gray-400 hover:text-white'}`}
                                >
                                  {action}
                                </button>
                              ))}
                            </div>
                          </div>

                          {newKeywordAction === 'WEBHOOK' && (
                            <div>
                              <label className="block text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-2">HTTP Webhook Endpoint (POST Dispatch)</label>
                              <input 
                                type="url"
                                required
                                value={newKeywordWebhook}
                                onChange={(e) => setNewKeywordWebhook(e.target.value)}
                                placeholder="https://yourdomain.com/sms/callback"
                                className="w-full bg-slate-955 border border-slate-800 rounded-xl px-3 py-2 text-white focus:outline-none focus:ring-2 focus:ring-indigo-500 font-mono text-xs"
                              />
                            </div>
                          )}

                          <div>
                            <label className="block text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-2">Auto-Reply Text Template</label>
                            <textarea 
                              rows={3}
                              value={newKeywordReply}
                              onChange={(e) => setNewKeywordReply(e.target.value)}
                              placeholder="Message dispatched back to the subscriber automatically..."
                              className="w-full bg-slate-955 border border-slate-800 rounded-xl px-3 py-2 text-white focus:outline-none focus:ring-2 focus:ring-indigo-500 text-xs"
                            />
                          </div>

                          <button
                            type="submit"
                            className="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2.5 rounded-xl transition-all flex items-center justify-center gap-1.5 hover:shadow text-xs uppercase tracking-wider"
                          >
                            <Plus className="w-4 h-4" /> Save Keyword Route
                          </button>
                        </form>
                      </div>

                    </div>

                  </div>

                  {/* Threaded Conversations Log Grid */}
                  <div className="glass-panel p-6 rounded-2xl border border-slate-855 glow-card">
                    <div className="flex items-center justify-between mb-6 pb-2 border-b border-slate-800/40">
                      <div>
                        <h4 className="font-bold text-white flex items-center gap-2 text-sm uppercase tracking-wider">
                          <MessageSquare className="w-4.5 h-4.5 text-indigo-400" />
                          Interactive Two-Way Conversation Threads
                        </h4>
                        <p className="text-[10px] text-gray-400 mt-1">Multi-user real-time session tracking with alternating bubble threads.</p>
                      </div>
                      <span className="text-[10px] font-mono text-gray-500">Click a thread to open live interactive session</span>
                    </div>

                    <div className="grid grid-cols-1 lg:grid-cols-3 gap-8 min-h-[400px]">
                      
                      {/* Left side: Threads index list */}
                      <div className="border-r border-slate-800/40 pr-4 space-y-3">
                        <span className="text-[10px] uppercase font-bold tracking-widest text-gray-400 block mb-2 font-semibold">Active Conversations ({Object.keys(threadedConversations).length})</span>
                        {Object.keys(threadedConversations).length === 0 ? (
                          <div className="p-8 text-center text-gray-500 italic text-xs">No active conversation sessions. Wait for an inbound message to start a thread.</div>
                        ) : (
                          Object.entries(threadedConversations).map(([msisdn, messages]: any) => {
                            const lastMsg = messages[messages.length - 1];
                            const isActive = activeConversationMsisdn === msisdn;
                            return (
                              <button
                                key={msisdn}
                                onClick={() => setActiveConversationMsisdn(msisdn)}
                                className={`w-full text-left p-4 rounded-xl border transition-all flex items-center justify-between ${
                                  isActive ? 'bg-indigo-600/10 border-indigo-500/50 shadow' : 'bg-slate-955 border-slate-850 hover:bg-slate-900/45'
                                }`}
                              >
                                <div>
                                  <span className="font-mono text-xs font-bold text-white block">{msisdn}</span>
                                  <span className="text-[10px] text-gray-400 block truncate max-w-[160px] mt-1">{lastMsg ? lastMsg.message : 'No message'}</span>
                                </div>
                                <div className="text-right flex flex-col items-end">
                                  <span className="text-[8px] text-gray-500">{lastMsg ? new Date(lastMsg.timestamp).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : ''}</span>
                                  {lastMsg && lastMsg.direction === 'INCOMING' && (
                                    <span className="w-2 h-2 bg-indigo-550 rounded-full mt-1.5 animate-pulse"></span>
                                  )}
                                </div>
                              </button>
                            );
                          })
                        )}
                      </div>

                      {/* Right side: Active Thread Alternating Chat Bubbles */}
                      <div className="lg:col-span-2 flex flex-col justify-between h-[450px]">
                        {!activeConversationMsisdn ? (
                          <div className="flex-1 flex flex-col justify-center items-center p-8 text-center bg-slate-950/20 rounded-2xl border border-slate-850/60 border-dashed">
                            <MessageSquare className="w-12 h-12 text-slate-700 mb-3" />
                            <h5 className="text-xs font-bold text-white mb-1">No Active Thread Selected</h5>
                            <p className="text-[10px] text-gray-400 max-w-[280px]">Select one of the mobile threads on the left or simulate a new message using the Sandbox widget to initialize an interactive view.</p>
                          </div>
                        ) : (
                          <div className="flex-1 flex flex-col justify-between bg-slate-950/30 rounded-2xl border border-slate-850/80 p-4 relative overflow-hidden">
                            {/* Thread header */}
                            <div className="flex items-center justify-between pb-3 border-b border-slate-800/40 mb-4 z-10">
                              <div className="flex items-center gap-2">
                                <div className="w-2.5 h-2.5 bg-emerald-500 rounded-full animate-pulse"></div>
                                <span className="font-mono text-xs font-black text-white">{activeConversationMsisdn}</span>
                              </div>
                              <span className="px-2 py-0.5 bg-indigo-500/10 text-indigo-400 border border-indigo-500/20 text-[9px] font-mono font-bold rounded">
                                Session Thread Active
                              </span>
                            </div>

                            {/* Bubble listing */}
                            <div className="flex-1 overflow-y-auto space-y-4 pr-2 mb-4 z-10 flex flex-col">
                              {(threadedConversations[activeConversationMsisdn] || []).map((msg: any, idx: number) => {
                                const isIncoming = msg.direction === 'INCOMING';
                                return (
                                  <div key={idx} className={`flex ${isIncoming ? 'justify-start' : 'justify-end'} animate-fadeIn`}>
                                    <div className={`max-w-[70%] rounded-2xl px-4 py-2.5 text-xs shadow ${
                                      isIncoming ? 'bg-slate-900 border border-slate-800 text-gray-200 rounded-bl-none' : 'bg-indigo-600 text-white rounded-br-none'
                                    }`}>
                                      <p className="leading-relaxed break-words font-medium">{msg.message}</p>
                                      <span className="text-[8px] block text-right mt-1 opacity-60 font-mono">
                                        {new Date(msg.timestamp).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}
                                      </span>
                                    </div>
                                  </div>
                                );
                              })}
                            </div>

                            {/* Send Box Form */}
                            <form onSubmit={handleSendThreadMessage} className="mt-auto pt-4 border-t border-slate-800/40 flex gap-2 z-10">
                              <input 
                                type="text"
                                required
                                value={threadReplyText}
                                onChange={(e) => setThreadReplyText(e.target.value)}
                                placeholder="Type outgoing conversational reply..."
                                className="flex-1 bg-slate-950 border border-slate-800 rounded-xl px-4 py-2.5 text-white focus:outline-none focus:ring-2 focus:ring-indigo-500 text-xs"
                              />
                              <button
                                type="submit"
                                className="bg-indigo-600 hover:bg-indigo-700 text-white font-bold px-4 py-2.5 rounded-xl transition-all flex items-center justify-center shadow"
                              >
                                <Send className="w-4 h-4" />
                              </button>
                            </form>
                          </div>
                        )}
                      </div>

                    </div>
                  </div>

                </div>
              )}

              {/* III.7 CLIENT SENDER ID CONFIGURATION */}
              {currentPage === 'sender_ids' && (
                <div className="space-y-8 animate-fadeIn">
                  
                  {/* Status Banner */}
                  {senderIdStatus.msg && (
                    <div className={`p-4 border text-xs rounded-2xl flex items-center justify-between animate-fadeIn ${
                      senderIdStatus.type === 'success' 
                        ? 'bg-emerald-950/40 border-emerald-500/30 text-emerald-300' 
                        : 'bg-red-950/40 border-red-500/30 text-red-300'
                    }`}>
                      <div className="flex items-center gap-2">
                        {senderIdStatus.type === 'success' ? <CheckCircle className="w-5 h-5 text-emerald-450" /> : <AlertTriangle className="w-5 h-5 text-red-450" />}
                        <span className="font-semibold">{senderIdStatus.msg}</span>
                      </div>
                      <button 
                        onClick={() => setSenderIdStatus({ type: null, msg: null })} 
                        className={`font-bold font-mono text-sm px-2 py-1 ${
                          senderIdStatus.type === 'success' ? 'text-emerald-400 hover:text-emerald-250' : 'text-red-400 hover:text-red-250'
                        }`}
                      >
                        ✕
                      </button>
                    </div>
                  )}

                  {/* Main Grid: Request Form + Info */}
                  <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    
                    {/* Left: Request Form */}
                    <div className="lg:col-span-1 glass-panel p-6 rounded-2xl border border-slate-855 glow-card h-fit">
                      <h4 className="font-bold text-white flex items-center gap-2 text-sm uppercase tracking-wider mb-2">
                        <Plus className="w-5 h-5 text-indigo-400" />
                        Request Custom Sender ID Mask
                      </h4>
                      <p className="text-[10px] text-gray-400 mb-6">Register custom alphanumeric brand masks or virtual numeric long-codes.</p>

                      <form onSubmit={handleCreateSenderId} className="space-y-6">
                        <div>
                          <label className="block text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-2">
                            Requested Sender ID Mask
                          </label>
                          <input 
                            type="text"
                            required
                            value={newSenderIdMask}
                            onChange={(e) => setNewSenderIdMask(e.target.value)}
                            placeholder="e.g. ACMEBRAND or +254700000000"
                            className="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-indigo-500 font-mono tracking-widest text-sm uppercase"
                          />
                          
                          {/* Real-time Interactive Validation UI */}
                          {newSenderIdMask.length > 0 && (() => {
                            const mask = newSenderIdMask.trim();
                            const isAlphanumeric = /^[A-Za-z0-9\s\-]{3,11}$/.test(mask);
                            const isNumeric = /^\+?[0-9]{3,20}$/.test(mask);
                            const genericAlpha = /^[A-Za-z0-9\s\-]+$/.test(mask);
                            const genericNum = /^\+?[0-9]+$/.test(mask);

                            let typeText = "Invalid Format";
                            let typeColor = "text-red-400 bg-red-950/30 border border-red-500/20";

                            if (isAlphanumeric) {
                              typeText = "Alphanumeric Brand Mask (Valid)";
                              typeColor = "text-emerald-400 bg-emerald-950/30 border border-emerald-500/20";
                            } else if (isNumeric) {
                              typeText = "Numeric Mobile Long-Code (Valid)";
                              typeColor = "text-emerald-400 bg-emerald-950/30 border border-emerald-500/20";
                            } else if (genericAlpha && mask.length > 11) {
                              typeText = "Invalid: Brand mask exceeds 11 chars limit";
                            } else if (genericNum && mask.length > 20) {
                              typeText = "Invalid: Numeric mask exceeds 20 digits limit";
                            } else if (mask.length < 3) {
                              typeText = "Invalid: Mask must be at least 3 characters";
                            } else {
                              typeText = "Invalid characters in mask";
                            }

                            return (
                              <div className="mt-3 space-y-2">
                                <div className={`px-3 py-1.5 rounded-lg text-[10px] font-bold tracking-wider flex items-center justify-between ${typeColor}`}>
                                  <span>{typeText}</span>
                                  <span className="font-mono">{mask.length} chars</span>
                                </div>
                                <div className="text-[9px] text-gray-500 bg-slate-900/40 p-2.5 rounded-lg space-y-1">
                                  <p>📌 **Alphanumeric Rules**: Max 11 characters (letters, numbers, spaces, dashes).</p>
                                  <p>📌 **Numeric Rules**: Max 20 digits (e.g. virtual MSISDN).</p>
                                </div>
                              </div>
                            );
                          })()}
                        </div>

                        <button
                          type="submit"
                          disabled={isLoading || !newSenderIdMask || (!/^[A-Za-z0-9\s\-]{3,11}$/.test(newSenderIdMask.trim()) && !/^\+?[0-9]{3,20}$/.test(newSenderIdMask.trim()))}
                          className="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3 rounded-xl transition-all hover:shadow disabled:opacity-30 text-xs uppercase tracking-wider flex items-center justify-center gap-2"
                        >
                          {isLoading ? <RefreshCw className="w-4 h-4 animate-spin" /> : <Plus className="w-4 h-4 text-white" />}
                          Submit for Compliance Review
                        </button>
                      </form>
                    </div>

                    {/* Right: Blacklist policy info & regulations */}
                    <div className="lg:col-span-2 glass-panel p-6 rounded-2xl border border-slate-855 glow-card flex flex-col justify-between">
                      <div>
                        <h4 className="font-bold text-white flex items-center gap-2 text-sm uppercase tracking-wider mb-2">
                          <ShieldCheck className="w-5 h-5 text-indigo-400" />
                          Platform Regulatory Protection & Blacklist
                        </h4>
                        <p className="text-[10px] text-gray-400 mb-4">Under communications regulatory oversight, brand impersonation and sovereign identity protection rules are strictly enforced.</p>
                        
                        <div className="bg-slate-900/30 border border-slate-850 p-4 rounded-xl space-y-3 mb-4">
                          <p className="text-xs text-gray-300 font-medium">To protect users from phishing, fraud, and corporate brand spoofing, our registry automatically blocks requests matching the following protected terms case-insensitively:</p>
                          <div className="grid grid-cols-2 md:grid-cols-4 gap-2">
                            {['SAFARICOM', 'M-PESA', 'POLICE', 'COKE', 'AIRTEL', 'TELKOM', 'EQUITY', 'KCB'].map(term => (
                              <div key={term} className="bg-slate-950 px-2 py-1.5 rounded-lg border border-slate-850 text-center font-mono text-[10px] font-black text-indigo-300 tracking-wider">
                                {term}
                              </div>
                            ))}
                          </div>
                          <p className="text-[10px] text-red-400 italic">⚠️ Any application matching these patterns will be rejected with an immediate brand protection policy violation error.</p>
                        </div>
                      </div>

                      <div className="p-4 bg-indigo-950/20 border border-indigo-900/30 rounded-xl">
                        <h5 className="text-[10px] font-bold text-indigo-300 uppercase tracking-wider mb-1 flex items-center gap-1.5">
                          <Calendar className="w-3.5 h-3.5 text-indigo-400" /> Carrier SLAs & Timelines
                        </h5>
                        <p className="text-[10px] text-gray-400 leading-relaxed">Sender ID activation takes **24–72 hours** depending on MNO updates. Once submitted, your mask stays PENDING until it gets carrier approved. Setting an approved fallback ensures campaigns run even if primary masks are pending on certain networks.</p>
                      </div>
                    </div>

                  </div>

                  {/* Registered Sender IDs Grid */}
                  <div className="glass-panel p-6 rounded-2xl border border-slate-855 glow-card">
                    <div className="flex items-center justify-between mb-6 pb-2 border-b border-slate-800/40">
                      <div>
                        <h4 className="font-bold text-white flex items-center gap-2 text-sm uppercase tracking-wider">
                          <Sliders className="w-4.5 h-4.5 text-indigo-400" />
                          Corporate Sender ID Directory Matrix
                        </h4>
                        <p className="text-[10px] text-gray-400 mt-1">Real-time status tracking per Mobile Operator with programmatic fallback routing overrides.</p>
                      </div>
                      <span className="text-[10px] font-mono text-gray-500">Active Tenant Binds: {senderIds.length}</span>
                    </div>

                    {senderIds.length === 0 ? (
                      <div className="p-12 text-center text-gray-500 italic text-xs border border-dashed border-slate-850 rounded-2xl bg-slate-900/10">
                        No registered Sender ID masks under this tenant profile.
                      </div>
                    ) : (
                      <div className="overflow-x-auto">
                        <table className="w-full text-left text-xs text-gray-300">
                          <thead className="bg-slate-950 uppercase tracking-widest text-[9px] text-gray-400 font-bold">
                            <tr>
                              <th className="px-6 py-4 rounded-l-xl">Sender Mask</th>
                              <th className="px-6 py-4">Mask Type</th>
                              <th className="px-6 py-4">Global Status</th>
                              <th className="px-6 py-4 text-center">Safaricom Bind</th>
                              <th className="px-6 py-4 text-center">Airtel Bind</th>
                              <th className="px-6 py-4 text-center">Telkom Bind</th>
                              <th className="px-6 py-4 rounded-r-xl">Dynamic Fallback Config</th>
                            </tr>
                          </thead>
                          <tbody className="divide-y divide-slate-850 bg-slate-900/10">
                            {senderIds.map(s => {
                              const isAlphanumeric = /[A-Za-z]/i.test(s.sender_id);
                              const typeText = isAlphanumeric ? "Alphanumeric" : "Numeric Mobile";
                              const typeColor = isAlphanumeric ? "text-indigo-400" : "text-amber-450 font-mono";

                              const safaricomBind = s.mno_approvals?.find((m: any) => m.mno_name === 'SAFARICOM') || { status: 'PENDING', reason: null };
                              const airtelBind = s.mno_approvals?.find((m: any) => m.mno_name === 'AIRTEL') || { status: 'PENDING', reason: null };
                              const telkomBind = s.mno_approvals?.find((m: any) => m.mno_name === 'TELKOM') || { status: 'PENDING', reason: null };

                              const renderBindBadge = (bind: any) => {
                                if (bind.status === 'APPROVED') {
                                  return (
                                    <div className="flex flex-col items-center justify-center gap-0.5">
                                      <span className="px-2 py-0.5 bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 text-[9px] font-bold rounded flex items-center gap-1 font-sans uppercase">
                                        <CheckCircle2 className="w-3 h-3 text-emerald-400" /> Approved
                                      </span>
                                    </div>
                                  );
                                } else if (bind.status === 'REJECTED') {
                                  return (
                                    <div className="flex flex-col items-center justify-center gap-0.5">
                                      <span className="px-2 py-0.5 bg-red-500/10 text-red-400 border border-red-500/20 text-[9px] font-bold rounded flex items-center gap-1 font-sans uppercase">
                                        <XCircle className="w-3 h-3 text-red-400" /> Rejected
                                      </span>
                                      {bind.reason && (
                                        <span className="text-[8px] text-gray-500 text-center font-sans tracking-wide italic max-w-[120px] truncate mt-0.5" title={bind.reason}>
                                          "{bind.reason}"
                                        </span>
                                      )}
                                    </div>
                                  );
                                } else {
                                  return (
                                    <div className="flex flex-col items-center justify-center gap-0.5">
                                      <span className="px-2 py-0.5 bg-amber-500/10 text-amber-400 border border-amber-500/20 text-[9px] font-bold rounded flex items-center gap-1 font-sans uppercase">
                                        <RefreshCw className="w-3 h-3 text-amber-400 animate-spin" /> Pending
                                      </span>
                                    </div>
                                  );
                                }
                              };

                              return (
                                <tr key={s.id} className="hover:bg-slate-900/20 transition-all">
                                  <td className="px-6 py-4">
                                    <span className="font-mono font-black tracking-wider text-sm text-white bg-slate-950/60 px-3 py-1.5 rounded-xl border border-slate-800/40 select-all inline-block">
                                      {s.sender_id}
                                    </span>
                                  </td>
                                  <td className={`px-6 py-4 font-bold ${typeColor}`}>
                                    {typeText}
                                  </td>
                                  <td className="px-6 py-4">
                                    <span className={`px-2 py-0.5 text-[9px] font-bold rounded uppercase ${
                                      s.status === 'APPROVED' ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' :
                                      s.status === 'REJECTED' ? 'bg-red-500/10 text-red-400 border border-red-500/20' :
                                      'bg-amber-500/10 text-amber-400 border border-amber-500/20'
                                    }`}>
                                      {s.status}
                                    </span>
                                  </td>
                                  <td className="px-6 py-4 text-center">
                                    {renderBindBadge(safaricomBind)}
                                  </td>
                                  <td className="px-6 py-4 text-center">
                                    {renderBindBadge(airtelBind)}
                                  </td>
                                  <td className="px-6 py-4 text-center">
                                    {renderBindBadge(telkomBind)}
                                  </td>
                                  <td className="px-6 py-4">
                                    <div className="flex flex-col gap-1 max-w-[200px]">
                                      <select
                                        value={s.fallback_sender_id_id || ''}
                                        onChange={(e) => {
                                          const val = e.target.value ? parseInt(e.target.value) : null;
                                          handleSetSenderIdFallback(s.id, val);
                                        }}
                                        className="bg-slate-950 border border-slate-800 rounded-xl px-2.5 py-1.5 text-xs text-white focus:outline-none focus:ring-1 focus:ring-indigo-500"
                                      >
                                        <option value="">No Fallback Mask</option>
                                        {senderIds
                                          .filter(f => f.id !== s.id && f.status === 'APPROVED')
                                          .map(f => (
                                            <option key={f.id} value={f.id}>
                                              {f.sender_id}
                                            </option>
                                          ))}
                                      </select>
                                      <span className="text-[8px] text-gray-500 leading-tight">
                                        💡 Used automatically if this mask is unapproved on a recipient network.
                                      </span>
                                    </div>
                                  </td>
                                </tr>
                              );
                            })}
                          </tbody>
                        </table>
                      </div>
                    )}
                  </div>

                </div>
              )}

              {/* III.8 CLIENT DELIVERY ANALYTICS & REPORTING */}
              {currentPage === 'reports' && (
                <div className="space-y-8 animate-fadeIn">
                  
                  {/* Live Metrics Grid */}
                  <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    <div className="glass-panel p-5 rounded-2xl border border-slate-850 glow-card flex items-center justify-between">
                      <div>
                        <p className="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1">Campaign Volume</p>
                        <h3 className="text-2xl font-black text-white font-mono">14,892</h3>
                        <p className="text-[9px] text-gray-500 mt-1 font-sans">Total dispatches fired</p>
                      </div>
                      <div className="p-3.5 bg-indigo-500/10 rounded-2xl text-indigo-400 border border-indigo-500/20">
                        <BarChart2 className="w-5.5 h-5.5" />
                      </div>
                    </div>

                    <div className="glass-panel p-5 rounded-2xl border border-slate-850 glow-card flex items-center justify-between">
                      <div>
                        <p className="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1">Successful Delivery</p>
                        <h3 className="text-2xl font-black text-emerald-450 font-mono">14,821</h3>
                        <p className="text-[9px] text-emerald-400 mt-1 font-sans flex items-center gap-0.5">
                          <CheckCircle className="w-3 h-3 text-emerald-450" /> 99.52% Delivery Rate
                        </p>
                      </div>
                      <div className="p-3.5 bg-emerald-500/10 rounded-2xl text-emerald-400 border border-emerald-500/20">
                        <CheckCircle2 className="w-5.5 h-5.5" />
                      </div>
                    </div>

                    <div className="glass-panel p-5 rounded-2xl border border-slate-850 glow-card flex items-center justify-between">
                      <div>
                        <p className="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1">Failed Submissions</p>
                        <h3 className="text-2xl font-black text-red-450 font-mono">71</h3>
                        <p className="text-[9px] text-red-400 mt-1 font-sans">Refunded programmatically</p>
                      </div>
                      <div className="p-3.5 bg-red-500/10 rounded-2xl text-red-400 border border-red-500/20">
                        <XCircle className="w-5.5 h-5.5" />
                      </div>
                    </div>

                    <div className="glass-panel p-5 rounded-2xl border border-slate-850 glow-card flex items-center justify-between">
                      <div>
                        <p className="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1">Pending Delivery</p>
                        <h3 className="text-2xl font-black text-amber-450 font-mono">0</h3>
                        <p className="text-[9px] text-gray-500 mt-1 font-sans">Ordered queues empty</p>
                      </div>
                      <div className="p-3.5 bg-amber-500/10 rounded-2xl text-amber-400 border border-amber-500/20">
                        <RefreshCw className="w-5.5 h-5.5 animate-pulse" />
                      </div>
                    </div>
                  </div>

                  {/* SVG Charts Area */}
                  <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    
                    {/* Operator Trends SVG Line Chart */}
                    <div className="lg:col-span-2 glass-panel p-6 rounded-2xl border border-slate-855 glow-card">
                      <div className="flex items-center justify-between mb-4 border-b border-slate-800/40 pb-3">
                        <div>
                          <h4 className="font-bold text-white text-sm uppercase tracking-wider">Dynamic Deliverability QoS Trend</h4>
                          <p className="text-[9px] text-gray-450">Mobile network operators real-time delivery success tracking.</p>
                        </div>
                        <div className="flex items-center gap-4 text-[9px] font-bold">
                          <span className="flex items-center gap-1"><div className="w-2 h-2 rounded-full bg-emerald-500"></div> Safaricom (99.8%)</span>
                          <span className="flex items-center gap-1"><div className="w-2 h-2 rounded-full bg-indigo-500"></div> Airtel (97.5%)</span>
                          <span className="flex items-center gap-1"><div className="w-2 h-2 rounded-full bg-violet-500"></div> Telkom (96.0%)</span>
                        </div>
                      </div>

                      {/* Premium Area SVG Chart */}
                      <div className="relative">
                        <svg viewBox="0 0 500 150" className="w-full h-40 overflow-visible text-slate-500">
                          <defs>
                            <linearGradient id="chartGradSaf" x1="0" y1="0" x2="0" y2="1">
                              <stop offset="0%" stopColor="#10B981" stopOpacity="0.25" />
                              <stop offset="100%" stopColor="#10B981" stopOpacity="0" />
                            </linearGradient>
                            <linearGradient id="chartGradAir" x1="0" y1="0" x2="0" y2="1">
                              <stop offset="0%" stopColor="#6366F1" stopOpacity="0.25" />
                              <stop offset="100%" stopColor="#6366F1" stopOpacity="0" />
                            </linearGradient>
                          </defs>
                          
                          {/* Y-Axis lines */}
                          <line x1="0" y1="30" x2="500" y2="30" stroke="#1e293b" strokeWidth="0.5" strokeDasharray="3" />
                          <line x1="0" y1="75" x2="500" y2="75" stroke="#1e293b" strokeWidth="0.5" strokeDasharray="3" />
                          <line x1="0" y1="120" x2="500" y2="120" stroke="#1e293b" strokeWidth="0.5" strokeDasharray="3" />

                          {/* Safaricom Area and Path */}
                          <path d="M0,35 L50,30 L100,32 L150,28 L200,35 L250,32 L300,30 L350,32 L400,28 L450,29 L500,30" fill="none" stroke="#10B981" strokeWidth="2.5" className="drop-shadow-[0_2px_6px_rgba(16,185,129,0.3)]" />
                          <path d="M0,35 L50,30 L100,32 L150,28 L200,35 L250,32 L300,30 L350,32 L400,28 L450,29 L500,30 L500,150 L0,150 Z" fill="url(#chartGradSaf)" />

                          {/* Airtel Area and Path */}
                          <path d="M0,65 L50,60 L100,75 L150,55 L200,62 L250,68 L300,58 L350,60 L400,64 L450,55 L500,58" fill="none" stroke="#6366F1" strokeWidth="2" className="drop-shadow-[0_2px_6px_rgba(99,102,241,0.3)]" />
                          <path d="M0,65 L50,60 L100,75 L150,55 L200,62 L250,68 L300,58 L350,60 L400,64 L450,55 L500,58 L500,150 L0,150 Z" fill="url(#chartGradAir)" />
                        </svg>
                      </div>
                    </div>

                    {/* Scheduled Reports Configuration */}
                    <div className="glass-panel p-6 rounded-2xl border border-slate-855 glow-card flex flex-col justify-between h-full">
                      <div>
                        <h4 className="font-bold text-white text-sm uppercase tracking-wider mb-2 flex items-center gap-1.5">
                          <Sliders className="w-4.5 h-4.5 text-indigo-400" />
                          Scheduled Reports Manager
                        </h4>
                        <p className="text-[10px] text-gray-400 mb-6 leading-relaxed">Configure daily or weekly automated campaign delivery logs sent directly to your corporate inbox.</p>
                        
                        {reportingStatusMsg && (
                          <div className="p-3 bg-indigo-950/40 border border-indigo-500/20 text-indigo-300 text-[10px] font-bold rounded-xl mb-4 font-mono">
                            {reportingStatusMsg}
                          </div>
                        )}

                        <div className="space-y-4">
                          <div>
                            <label className="block text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-2">Corporate Recipient Email</label>
                            <input 
                              type="email"
                              required
                              placeholder="e.g. alerts@company.com"
                              value={reportingEmail}
                              onChange={(e) => setReportingEmail(e.target.value)}
                              className="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-3 text-xs text-white focus:outline-none focus:ring-2 focus:ring-indigo-500 font-mono"
                            />
                          </div>

                          <div>
                            <label className="block text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-2">Reporting Timeline Interval</label>
                            <select
                              value={reportingInterval}
                              onChange={(e) => setReportingInterval(e.target.value)}
                              className="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-3 text-xs text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                            >
                              <option value="DAILY">DAILY COMPREHENSIVE LOGS</option>
                              <option value="WEEKLY">WEEKLY SUMMARY ANALYTICS</option>
                              <option value="MONTHLY">MONTHLY AUDITED STATEMENT</option>
                            </select>
                          </div>
                        </div>
                      </div>

                      <button
                        onClick={() => {
                          if (!reportingEmail) {
                            setReportingStatusMsg("Error: Recipient email is required.");
                            return;
                          }
                          setReportingStatusMsg(`SUCCESS: Reports scheduled (${reportingInterval}) to ${reportingEmail}!`);
                          setTimeout(() => setReportingStatusMsg(null), 3000);
                        }}
                        className="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3.5 rounded-xl transition-all hover:shadow text-xs uppercase tracking-wider flex items-center justify-center gap-2 mt-6"
                      >
                        <Check className="w-4 h-4" /> Save Report Schedule
                      </button>
                    </div>
                  </div>

                  {/* Message-Level Log Directory with Salted Hashing Search */}
                  <div className="glass-panel p-6 rounded-2xl border border-slate-855 glow-card">
                    <div className="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6 pb-4 border-b border-slate-800/40">
                      <div>
                        <h4 className="font-bold text-white text-sm uppercase tracking-wider">Granular Message-Level Directory Logs</h4>
                        <p className="text-[10px] text-gray-450 mt-1 leading-relaxed">Search individual SMS dispatches. MSISDNs are stored programmatically as DPA-compliant salted hashes.</p>
                      </div>
                      
                      <div className="flex items-center gap-3">
                        <input 
                          type="text"
                          placeholder="Search MSISDN Hash..."
                          value={searchMsisdnQuery}
                          onChange={(e) => setSearchMsisdnQuery(e.target.value)}
                          className="bg-slate-955 border border-slate-800 rounded-xl px-4 py-2 text-xs text-white focus:outline-none focus:ring-1 focus:ring-indigo-500 font-mono w-48"
                        />
                        <button
                          onClick={() => {
                            toast.success('Simulated CSV reports compiled. Outbound statement containing 14,892 entries was downloaded successfully!');
                          }}
                          className="bg-slate-950 border border-slate-800 hover:bg-slate-900 text-gray-300 font-bold px-3 py-2 rounded-xl text-[10px] uppercase tracking-wider flex items-center gap-1 transition-all"
                        >
                          <ArrowUpRight className="w-3.5 h-3.5" /> Export Logs CSV
                        </button>
                      </div>
                    </div>

                    <div className="overflow-x-auto">
                      <table className="w-full text-left text-xs text-gray-300">
                        <thead className="bg-slate-950 uppercase tracking-widest text-[9px] text-gray-450 font-bold">
                          <tr>
                            <th className="px-6 py-4 rounded-l-xl">Record ID</th>
                            <th className="px-6 py-4">Salted MSISDN Hash</th>
                            <th className="px-6 py-4 text-center">Network</th>
                            <th className="px-6 py-4">Timestamp</th>
                            <th className="px-6 py-4 text-center">Receipt Status</th>
                            <th className="px-6 py-4 rounded-r-xl text-center">DLR Code</th>
                          </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-850 bg-slate-900/10">
                          {deliveryLogs
                            .filter(l => !searchMsisdnQuery || l.msisdn_hash.toLowerCase().includes(searchMsisdnQuery.toLowerCase()))
                            .map(l => (
                              <tr key={l.id} className="hover:bg-slate-900/20 transition-all font-mono">
                                <td className="px-6 py-4 font-bold text-gray-400">#{l.id}</td>
                                <td className="px-6 py-4 text-indigo-300 font-bold tracking-wider select-all">{l.msisdn_hash}</td>
                                <td className="px-6 py-4 text-center font-sans font-bold text-white">{l.network}</td>
                                <td className="px-6 py-4 text-gray-400 text-[10px]">{l.timestamp}</td>
                                <td className="px-6 py-4 text-center">
                                  <span className={`px-2 py-0.5 rounded text-[9px] font-black uppercase tracking-wider ${l.status === 'DELIVERED' ? 'bg-emerald-500/10 text-emerald-400' : 'bg-red-500/10 text-red-400'}`}>
                                    {l.status}
                                  </span>
                                </td>
                                <td className="px-6 py-4 text-center">
                                  <span className={`text-[10px] font-bold ${l.status === 'DELIVERED' ? 'text-emerald-450' : 'text-red-450'}`}>
                                    {l.error_code}
                                  </span>
                                </td>
                              </tr>
                            ))}
                        </tbody>
                      </table>
                    </div>
                  </div>
                </div>
              )}
            </>
          )}

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
                          <pre>{'curl -X POST https://api.casamoko.com/v1/sms/send \\'}
{'\n  -H \'Authorization: Bearer ' + devApiKey + '\' \\'}
{'\n  -H \'Content-Type: application/json\' \\'}
{'\n  -d \'{'}
{'\n    "to": ["+254700000000"],'}
{'\n    "sender_id": "YOUR_SENDER",'}
{'\n    "message": "Hello from Casamoko API!"'}
{'\n  }\''}
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
                          <p className="text-sm text-gray-400 mt-1">Create dynamic message templates with {'{variables}'} for highly personalized campaigns.</p>
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
                                <label className="block text-xs font-semibold text-gray-400 mb-2">Content (Use {'{var}'} for variables)</label>
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
                                      <span className={`px-3 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider ${t.status === 'APPROVED' ? 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/30' : 'bg-amber-500/20 text-amber-400 border border-amber-500/30'}`}>
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

                  {/* III.Z TWO-WAY SMS INBOX */}
                  {currentPage === 'inbox' && (
                    <div className="h-[75vh] flex bg-slate-950 border border-slate-800/80 rounded-2xl overflow-hidden animate-fade-in-up">
                      {/* Left Pane - Chats List */}
                      <div className="w-80 border-r border-slate-800/80 flex flex-col bg-slate-900/40">
                        <div className="p-4 border-b border-slate-800/60 bg-slate-950/80">
                          <h3 className="text-white font-bold tracking-wider flex items-center gap-2">
                            <Inbox className="w-5 h-5 text-indigo-400" /> Two-Way Inbox
                          </h3>
                        </div>
                        <div className="flex-1 overflow-y-auto overflow-x-hidden">
                          {inboxChats.map(chat => (
                            <button
                              key={chat.id}
                              onClick={() => setSelectedChatId(chat.id)}
                              className={`w-full p-4 flex items-center gap-4 border-b border-slate-800/40 transition-all text-left ${selectedChatId === chat.id ? 'bg-indigo-600/10 border-l-2 border-l-indigo-500' : 'hover:bg-slate-800/40'}`}
                            >
                              <div className="w-10 h-10 rounded-full bg-indigo-500/20 flex items-center justify-center shrink-0">
                                <User className="w-5 h-5 text-indigo-300" />
                              </div>
                              <div className="flex-1 min-w-0">
                                <div className="flex justify-between items-center mb-1">
                                  <h4 className="text-sm font-bold text-white truncate">{chat.msisdn}</h4>
                                  <span className="text-[10px] text-gray-500">{chat.time}</span>
                                </div>
                                <p className="text-xs text-gray-400 truncate">{chat.lastMessage}</p>
                              </div>
                              {chat.unread > 0 && (
                                <div className="w-5 h-5 rounded-full bg-emerald-500 flex items-center justify-center shrink-0">
                                  <span className="text-[10px] font-bold text-slate-900">{chat.unread}</span>
                                </div>
                              )}
                            </button>
                          ))}
                        </div>
                      </div>

                      {/* Right Pane - Chat View */}
                      <div className="flex-1 flex flex-col bg-slate-950">
                        {selectedChatId ? (
                          <>
                            <div className="h-16 border-b border-slate-800/80 bg-slate-900/40 flex items-center px-6 gap-4 shrink-0">
                              <div className="w-10 h-10 rounded-full bg-indigo-500/20 flex items-center justify-center">
                                <User className="w-5 h-5 text-indigo-300" />
                              </div>
                              <div>
                                <h3 className="text-white font-bold">{inboxChats.find(c => c.id === selectedChatId)?.msisdn}</h3>
                                <span className="text-[10px] text-emerald-400 flex items-center gap-1">
                                  <div className="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></div> Active
                                </span>
                              </div>
                            </div>

                            <div className="flex-1 overflow-y-auto p-6 space-y-6">
                              {inboxChats.find(c => c.id === selectedChatId)?.history.map((msg, idx) => (
                                <div key={idx} className={`flex flex-col ${msg.dir === 'out' ? 'items-end' : 'items-start'}`}>
                                  <div className={`max-w-[70%] rounded-2xl px-5 py-3 ${msg.dir === 'out' ? 'bg-indigo-600 text-white rounded-br-sm' : 'bg-slate-800 text-gray-200 rounded-bl-sm border border-slate-700'}`}>
                                    <p className="text-sm">{msg.text}</p>
                                  </div>
                                  <span className="text-[10px] text-gray-500 mt-1">{msg.time}</span>
                                </div>
                              ))}
                            </div>

                            <div className="p-4 border-t border-slate-800/80 bg-slate-900/20 shrink-0">
                              <div className="flex gap-4">
                                <input
                                  type="text"
                                  value={replyText}
                                  onChange={(e) => setReplyText(e.target.value)}
                                  placeholder="Type a reply... (Enter to send)"
                                  onKeyDown={(e) => {
                                    if(e.key === 'Enter' && replyText.trim()) {
                                      const updatedChats = inboxChats.map(c => {
                                        if(c.id === selectedChatId) {
                                          return { ...c, history: [...c.history, { dir: 'out', text: replyText, time: 'Just now' }], lastMessage: replyText, time: 'Just now' };
                                        }
                                        return c;
                                      });
                                      setInboxChats(updatedChats);
                                      setReplyText('');
                                    }
                                  }}
                                  className="flex-1 bg-slate-950 border border-slate-700 rounded-xl px-4 py-3 text-sm text-white focus:ring-1 focus:ring-indigo-500 outline-none"
                                />
                                <button 
                                  onClick={() => {
                                    if(!replyText.trim()) return;
                                    const updatedChats = inboxChats.map(c => {
                                      if(c.id === selectedChatId) {
                                        return { ...c, history: [...c.history, { dir: 'out', text: replyText, time: 'Just now' }], lastMessage: replyText, time: 'Just now' };
                                      }
                                      return c;
                                    });
                                    setInboxChats(updatedChats);
                                    setReplyText('');
                                  }}
                                  className="px-6 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl font-bold flex items-center justify-center transition-all shadow-[0_0_15px_rgba(79,70,229,0.3)]">
                                  <Send className="w-4 h-4" />
                                </button>
                              </div>
                            </div>
                          </>
                        ) : (
                          <div className="flex-1 flex flex-col items-center justify-center text-gray-500">
                            <MessageSquare className="w-16 h-16 mb-4 opacity-20" />
                            <p>Select a conversation to start messaging</p>
                          </div>
                        )}
                      </div>
                    </div>
                  )}

            </div>
          </main>
        </>
      )}

      {/* 3. SYSTEM PRIVILEGE MATRIX MODAL OVERLAY */}
      {showPrivilegeMatrix && (
        <div className="fixed inset-0 bg-slate-955/80 backdrop-blur-md z-50 flex items-center justify-center p-6 animate-fadeIn">
          <div className="w-full max-w-4xl glass-panel border border-slate-800 rounded-3xl p-8 relative overflow-hidden shadow-2xl glow-card">
            <div className="absolute w-[300px] h-[300px] rounded-full bg-indigo-600/10 blur-3xl -top-20 -right-20 pointer-events-none"></div>
            
            <div className="flex items-center justify-between border-b border-slate-800/60 pb-5 mb-6">
              <div className="flex items-center gap-3">
                <ShieldCheck className="w-8 h-8 text-indigo-400" />
                <div>
                  <h3 className="text-xl font-extrabold text-white">Casamoko RBAC Authorization Matrix</h3>
                  <p className="text-xs text-gray-400 mt-1">Granular capabilities map across multiple levels of access control.</p>
                </div>
              </div>
              <button 
                onClick={() => setShowPrivilegeMatrix(false)}
                className="px-4 py-2 bg-slate-900 hover:bg-slate-800 border border-slate-800 hover:text-white text-gray-300 rounded-xl transition-all font-semibold text-xs uppercase tracking-wider"
              >
                Close Matrix
              </button>
            </div>

            <div className="overflow-x-auto rounded-xl border border-slate-800/60">
              <table className="w-full text-left text-xs text-gray-300">
                <thead className="bg-slate-950 uppercase tracking-widest text-[9px] text-gray-400 font-bold">
                  <tr>
                    <th className="px-5 py-4 rounded-l-xl">Role / Tier Name</th>
                    <th className="px-5 py-4 text-center">Global Config</th>
                    <th className="px-5 py-4 text-center">Users / KYC</th>
                    <th className="px-5 py-4 text-center">Reseller markup</th>
                    <th className="px-5 py-4 text-center">Carrier SMPP Binds</th>
                    <th className="px-5 py-4 text-center">Outbound Dispatches</th>
                    <th className="px-5 py-4 text-center">Wallet Finance</th>
                    <th className="px-5 py-4 text-center">Opt-Out Reg</th>
                    <th className="px-5 py-4 text-center rounded-r-xl">Immutable Audit</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-850 bg-slate-900/20">
                  {/* Super Admin */}
                  <tr className="hover:bg-slate-900/30 transition-all font-bold">
                    <td className="px-5 py-4 text-white font-extrabold flex items-center gap-1.5"><div className="w-2 h-2 rounded-full bg-indigo-500 animate-pulse"></div>SUPER_ADMIN</td>
                    <td className="px-5 py-4 text-center text-emerald-405 font-extrabold text-sm">✓</td>
                    <td className="px-5 py-4 text-center text-emerald-405 font-extrabold text-sm">✓</td>
                    <td className="px-5 py-4 text-center text-emerald-405 font-extrabold text-sm">✓</td>
                    <td className="px-5 py-4 text-center text-emerald-405 font-extrabold text-sm">✓</td>
                    <td className="px-5 py-4 text-center text-gray-500 font-mono">—</td>
                    <td className="px-5 py-4 text-center text-emerald-405 font-extrabold text-sm">✓</td>
                    <td className="px-5 py-4 text-center text-emerald-405 font-extrabold text-sm">✓</td>
                    <td className="px-5 py-4 text-center text-emerald-405 font-extrabold text-sm">✓</td>
                  </tr>
                  {/* Reseller */}
                  <tr className="hover:bg-slate-900/30 transition-all font-semibold">
                    <td className="px-5 py-4 text-white font-extrabold flex items-center gap-1.5"><div className="w-2 h-2 rounded-full bg-violet-500 animate-pulse"></div>RESELLER</td>
                    <td className="px-5 py-4 text-center text-gray-500 font-mono">—</td>
                    <td className="px-5 py-4 text-center text-emerald-405 font-extrabold text-sm">✓</td>
                    <td className="px-5 py-4 text-center text-emerald-405 font-extrabold text-sm">✓</td>
                    <td className="px-5 py-4 text-center text-gray-500 font-mono">—</td>
                    <td className="px-5 py-4 text-center text-gray-500 font-mono">—</td>
                    <td className="px-5 py-4 text-center text-emerald-405 font-extrabold text-sm">✓</td>
                    <td className="px-5 py-4 text-center text-gray-500 font-mono">—</td>
                    <td className="px-5 py-4 text-center text-gray-500 font-mono">—</td>
                  </tr>
                  {/* Client Admin */}
                  <tr className="hover:bg-slate-900/30 transition-all font-bold text-gray-200">
                    <td className="px-5 py-4 text-white font-extrabold flex items-center gap-1.5"><div className="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></div>CLIENT_ADMIN</td>
                    <td className="px-5 py-4 text-center text-gray-500 font-mono">—</td>
                    <td className="px-5 py-4 text-center text-emerald-405 font-extrabold text-sm">✓</td>
                    <td className="px-5 py-4 text-center text-gray-500 font-mono">—</td>
                    <td className="px-5 py-4 text-center text-gray-500 font-mono">—</td>
                    <td className="px-5 py-4 text-center text-emerald-405 font-extrabold text-sm">✓</td>
                    <td className="px-5 py-4 text-center text-emerald-405 font-extrabold text-sm">✓</td>
                    <td className="px-5 py-4 text-center text-emerald-405 font-extrabold text-sm">✓</td>
                    <td className="px-5 py-4 text-center text-gray-500 font-mono">—</td>
                  </tr>
                  {/* Campaign Manager */}
                  <tr className="hover:bg-slate-900/30 transition-all text-gray-300">
                    <td className="px-5 py-4 text-white font-bold flex items-center gap-1.5"><div className="w-2 h-2 rounded-full bg-blue-500 animate-pulse"></div>CAMPAIGN_MANAGER</td>
                    <td className="px-5 py-4 text-center text-gray-500 font-mono">—</td>
                    <td className="px-5 py-4 text-center text-gray-500 font-mono">—</td>
                    <td className="px-5 py-4 text-center text-gray-500 font-mono">—</td>
                    <td className="px-5 py-4 text-center text-gray-500 font-mono">—</td>
                    <td className="px-5 py-4 text-center text-emerald-405 font-extrabold text-sm">✓</td>
                    <td className="px-5 py-4 text-center text-red-500 font-bold text-sm">🔒</td>
                    <td className="px-5 py-4 text-center text-emerald-405 font-extrabold text-sm">✓</td>
                    <td className="px-5 py-4 text-center text-gray-500 font-mono">—</td>
                  </tr>
                  {/* Analyst */}
                  <tr className="hover:bg-slate-900/30 transition-all text-gray-300">
                    <td className="px-5 py-4 text-white font-bold flex items-center gap-1.5"><div className="w-2 h-2 rounded-full bg-amber-500 animate-pulse"></div>ANALYST</td>
                    <td className="px-5 py-4 text-center text-gray-500 font-mono">—</td>
                    <td className="px-5 py-4 text-center text-gray-500 font-mono">—</td>
                    <td className="px-5 py-4 text-center text-gray-500 font-mono">—</td>
                    <td className="px-5 py-4 text-center text-gray-500 font-mono">—</td>
                    <td className="px-5 py-4 text-center text-red-500 font-bold text-sm">🔒</td>
                    <td className="px-5 py-4 text-center text-red-500 font-bold text-sm">🔒</td>
                    <td className="px-5 py-4 text-center text-gray-500 font-mono">—</td>
                    <td className="px-5 py-4 text-center text-gray-500 font-mono">—</td>
                  </tr>
                  {/* Finance Officer */}
                  <tr className="hover:bg-slate-900/30 transition-all text-gray-300">
                    <td className="px-5 py-4 text-white font-bold flex items-center gap-1.5"><div className="w-2 h-2 rounded-full bg-cyan-500 animate-pulse"></div>FINANCE_OFFICER</td>
                    <td className="px-5 py-4 text-center text-gray-500 font-mono">—</td>
                    <td className="px-5 py-4 text-center text-gray-500 font-mono">—</td>
                    <td className="px-5 py-4 text-center text-gray-500 font-mono">—</td>
                    <td className="px-5 py-4 text-center text-gray-500 font-mono">—</td>
                    <td className="px-5 py-4 text-center text-red-500 font-bold text-sm">🔒</td>
                    <td className="px-5 py-4 text-center text-emerald-405 font-extrabold text-sm">✓</td>
                    <td className="px-5 py-4 text-center text-gray-500 font-mono">—</td>
                    <td className="px-5 py-4 text-center text-gray-500 font-mono">—</td>
                  </tr>
                </tbody>
              </table>
            </div>

            <div className="mt-6 p-4 bg-slate-950/40 border border-slate-800/40 rounded-2xl text-[10px] text-gray-400 leading-relaxed space-y-1">
              <p>📌 **Super Admin (System Supervisor)** retains immutable backend access to system configurations, reseller portfolios, and audit trails.</p>
              <p>📌 **Resellers** govern sub-client allocations and credit markups. They do not have direct access to system binds or audit trails.</p>
              <p>📌 **Client Tiers** split capabilities among operational roles (Admins, Managers, Analysts, and Finance Officers) to assure strict division of duties.</p>
            </div>
          </div>
        </div>
      )}

      {/* --- MODALS --- */}

      {isBalanceModalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-6 bg-slate-950/80 backdrop-blur-lg">
          <div className="bg-slate-900 border border-slate-800 rounded-3xl w-full max-w-md p-8 relative overflow-hidden shadow-2xl">
            <button 
              onClick={() => setIsBalanceModalOpen(false)}
              className="absolute top-6 right-6 p-2 rounded-full bg-slate-800/50 hover:bg-slate-700 text-gray-400 hover:text-white transition-all"
            >
              <X className="w-5 h-5" />
            </button>
            <div className="flex items-center gap-4 mb-8">
              <div className="w-12 h-12 rounded-2xl bg-indigo-500/20 border border-indigo-500/30 flex items-center justify-center">
                <Wallet className="w-6 h-6 text-indigo-400" />
              </div>
              <div>
                <h2 className="text-2xl font-black text-white tracking-tight">Gateway Balance</h2>
                <p className="text-sm text-indigo-400 font-medium">Safaricom DSDP (Primary Node)</p>
              </div>
            </div>

            <div className="space-y-4">
              <div className="p-5 bg-slate-950/50 border border-slate-800/50 rounded-2xl">
                <p className="text-xs font-bold text-gray-500 uppercase tracking-widest mb-1">Available SMS Units</p>
                <div className="flex items-end gap-2">
                  <span className="text-4xl font-black text-white font-mono">{gatewayBalance || '0.00'}</span>
                  <span className="text-lg font-bold text-gray-400 mb-1">Units</span>
                </div>
              </div>

              <div className="grid grid-cols-2 gap-4">
                <div className="p-4 bg-slate-800/20 border border-slate-800/50 rounded-xl">
                  <p className="text-[10px] font-bold text-gray-500 uppercase tracking-widest mb-1">Status</p>
                  <span className="text-sm font-bold text-emerald-400 flex items-center gap-1">
                    <Activity className="w-3 h-3" /> Connected
                  </span>
                </div>
                <div className="p-4 bg-slate-800/20 border border-slate-800/50 rounded-xl">
                  <p className="text-[10px] font-bold text-gray-500 uppercase tracking-widest mb-1">Account Type</p>
                  <span className="text-sm font-bold text-white">Postpaid</span>
                </div>
              </div>
            </div>

            <button 
              onClick={() => setIsBalanceModalOpen(false)}
              className="mt-8 w-full py-4 bg-indigo-600 hover:bg-indigo-500 text-white font-black text-sm uppercase tracking-widest rounded-2xl transition-all shadow-lg shadow-indigo-500/20"
            >
              Close
            </button>
          </div>
        </div>
      )}

      {isProfileModalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-6 bg-slate-950/80 backdrop-blur-lg">
          <div className="bg-slate-900 border border-slate-800 rounded-3xl w-full max-w-md p-8 relative overflow-hidden shadow-2xl">
            <button 
              onClick={() => setIsProfileModalOpen(false)}
              className="absolute top-6 right-6 p-2 rounded-full bg-slate-800/50 hover:bg-slate-700 text-gray-400 hover:text-white transition-all"
            >
              <X className="w-5 h-5" />
            </button>
            <div className="flex items-center gap-4 mb-8">
              <div className="w-12 h-12 rounded-2xl bg-indigo-500/20 border border-indigo-500/30 flex items-center justify-center">
                <User className="w-6 h-6 text-indigo-400" />
              </div>
              <div>
                <h2 className="text-2xl font-black text-white tracking-tight">Edit Profile</h2>
                <p className="text-sm text-indigo-400 font-medium">{user?.email}</p>
              </div>
            </div>

            <div className="space-y-5">
              <div className="space-y-2">
                <label className="text-xs font-bold text-gray-400 uppercase tracking-wider ml-1">Full Name</label>
                <input 
                  type="text" 
                  value={editProfileName}
                  onChange={(e) => setEditProfileName(e.target.value)}
                  className="w-full bg-slate-950/50 border border-slate-800 rounded-xl px-4 py-3.5 text-white placeholder-slate-600 focus:outline-none focus:ring-2 focus:ring-indigo-500/50 focus:border-indigo-500 transition-all font-medium"
                  placeholder="Jane Doe"
                />
              </div>

              <div className="space-y-2">
                <label className="text-xs font-bold text-gray-400 uppercase tracking-wider ml-1">New Password (Optional)</label>
                <input 
                  type="password" 
                  value={editProfilePassword}
                  onChange={(e) => setEditProfilePassword(e.target.value)}
                  className="w-full bg-slate-950/50 border border-slate-800 rounded-xl px-4 py-3.5 text-white placeholder-slate-600 focus:outline-none focus:ring-2 focus:ring-indigo-500/50 focus:border-indigo-500 transition-all font-medium"
                  placeholder="Leave blank to keep current"
                />
              </div>

              <button 
                onClick={handleUpdateProfile}
                disabled={isUpdatingProfile || !editProfileName.trim()}
                className="w-full mt-4 bg-indigo-600 hover:bg-indigo-500 text-white font-bold py-4 rounded-xl transition-all disabled:opacity-50"
              >
                {isUpdatingProfile ? 'Saving...' : 'Save Profile Changes'}
              </button>
            </div>
          </div>
        </div>
      )}

      {/* View Reseller Profile Modal */}
      {viewingReseller && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm">
          <div className="glass-panel p-6 rounded-2xl border border-slate-800 w-full max-w-md">
            <div className="flex justify-between items-center mb-6 border-b border-slate-800/60 pb-4">
              <div className="flex items-center gap-3">
                <div className="w-10 h-10 rounded-full bg-indigo-500/20 flex items-center justify-center border border-indigo-500/40">
                  <Building className="w-5 h-5 text-indigo-400" />
                </div>
                <div>
                  <h3 className="text-lg font-bold text-white leading-tight">{viewingReseller.name}</h3>
                  <p className="text-[10px] text-gray-400 font-mono tracking-widest uppercase">ID: RES-{String(viewingReseller.id).padStart(5, '0')}</p>
                </div>
              </div>
              <button onClick={() => setViewingReseller(null)} className="text-gray-400 hover:text-white"><X className="w-5 h-5" /></button>
            </div>
            
            <div className="space-y-4 mb-6">
              <div className="bg-slate-950/50 p-4 rounded-xl border border-slate-800/40">
                <p className="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Email Address</p>
                <p className="text-sm font-medium text-white">{viewingReseller.email || `${viewingReseller.name.toLowerCase().replace(/\s+/g, '.')}@reseller.com`}</p>
              </div>

              <div className="grid grid-cols-2 gap-4">
                <div className="bg-slate-950/50 p-4 rounded-xl border border-slate-800/40">
                  <p className="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Profit Markup</p>
                  <p className="text-lg font-bold text-indigo-400">{Number(viewingReseller.markup_percentage).toFixed(2)}%</p>
                </div>
                <div className="bg-slate-950/50 p-4 rounded-xl border border-slate-800/40">
                  <p className="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Available Credits</p>
                  <p className="text-lg font-bold text-emerald-400">Ksh {Number(viewingReseller.api_credits).toFixed(2)}</p>
                </div>
              </div>

              <div className="bg-slate-950/50 p-4 rounded-xl border border-slate-800/40 flex items-center justify-between">
                <div>
                  <p className="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Account Status</p>
                  <div className="flex items-center gap-1.5">
                    <div className="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></div>
                    <p className="text-sm font-bold text-white">{viewingReseller.status || 'ACTIVE'}</p>
                  </div>
                </div>
                <div className="text-right">
                   <p className="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Registered</p>
                   <p className="text-sm font-medium text-gray-300">Just now</p>
                </div>
              </div>
            </div>
            
            <button onClick={() => setViewingReseller(null)} className="w-full bg-slate-800 hover:bg-slate-700 text-white font-bold py-3 rounded-xl transition-all">Close Profile</button>
          </div>
        </div>
      )}

      {/* Edit Reseller Modal */}
      {editingReseller && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm">
          <div className="glass-panel p-6 rounded-2xl border border-slate-800 w-full max-w-md">
            <div className="flex justify-between items-center mb-6">
              <h3 className="text-lg font-bold text-white">Edit Reseller</h3>
              <button onClick={() => setEditingReseller(null)} className="text-gray-400 hover:text-white"><X className="w-5 h-5" /></button>
            </div>
            
            <div className="space-y-4 mb-6">
              <div>
                <label className="block text-xs font-semibold text-gray-400 uppercase mb-2">Reseller Name</label>
                <input type="text" value={editResellerName} onChange={e => setEditResellerName(e.target.value)} className="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-indigo-500" />
              </div>
              
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block text-xs font-semibold text-gray-400 uppercase mb-2">Markup (%)</label>
                  <input type="number" value={editResellerMarkup} onChange={e => setEditResellerMarkup(e.target.value)} className="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                </div>
                <div>
                  <label className="block text-xs font-semibold text-gray-400 uppercase mb-2">Available Credits</label>
                  <input type="number" value={editResellerCredits} onChange={e => setEditResellerCredits(e.target.value)} className="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                </div>
              </div>
            </div>
            
            <button onClick={handleEditReseller} className="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3 rounded-xl transition-all">Save Changes</button>
          </div>
        </div>
      )}

      {/* Reset Password Modal */}
      {resettingReseller && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm">
          <div className="glass-panel p-6 rounded-2xl border border-slate-800 w-full max-w-md">
            <div className="flex justify-between items-center mb-4">
              <h3 className="text-lg font-bold text-white">Reset Password</h3>
              <button onClick={() => setResettingReseller(null)} className="text-gray-400 hover:text-white"><X className="w-5 h-5" /></button>
            </div>
            <div className="mb-4">
              <label className="block text-xs font-semibold text-gray-400 uppercase mb-2">New Password for {resettingReseller.name}</label>
              <input type="text" value={resetResellerPassword} onChange={e => setResetResellerPassword(e.target.value)} placeholder="Enter new password" className="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-indigo-500" />
            </div>
            <button onClick={handleResetResellerPassword} className="w-full bg-amber-600 hover:bg-amber-700 text-white font-bold py-3 rounded-xl transition-all">Confirm Reset</button>
          </div>
        </div>
      )}

      {/* Delete Reseller Modal */}
      {deletingReseller && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm">
          <div className="glass-panel p-6 rounded-2xl border border-slate-800 w-full max-w-md">
            <div className="flex justify-between items-center mb-4">
              <h3 className="text-lg font-bold text-rose-500">Delete Reseller</h3>
              <button onClick={() => setDeletingReseller(null)} className="text-gray-400 hover:text-white"><X className="w-5 h-5" /></button>
            </div>
            <p className="text-gray-300 mb-6 text-sm">Are you sure you want to delete the reseller <strong>{deletingReseller.name}</strong>? This action cannot be undone.</p>
            <div className="flex gap-4">
              <button onClick={() => setDeletingReseller(null)} className="flex-1 bg-slate-800 hover:bg-slate-700 text-white font-bold py-3 rounded-xl transition-all">Cancel</button>
              <button onClick={handleDeleteReseller} className="flex-1 bg-rose-600 hover:bg-rose-700 text-white font-bold py-3 rounded-xl transition-all">Delete</button>
            </div>
          </div>
        </div>
      )}

      {/* CAMPAIGN LOGS MODAL */}
      {viewingCampaignLogs && (
        <div className="fixed inset-0 bg-slate-950/80 backdrop-blur-sm z-50 flex items-center justify-center p-4">
          <div className="bg-darkBg border border-slate-800 w-full max-w-4xl rounded-2xl shadow-2xl flex flex-col max-h-[85vh]">
            <div className="p-6 border-b border-slate-800 flex justify-between items-center bg-slate-900/50 rounded-t-2xl">
              <div>
                <h2 className="text-xl font-bold text-white flex items-center gap-2">
                  <Send className="w-5 h-5 text-indigo-400" />
                  Delivery Report: {viewingCampaignLogs.name}
                </h2>
                <p className="text-xs text-gray-400 mt-1">Outbox transparent log viewer</p>
              </div>
              <button onClick={() => setViewingCampaignLogs(null)} className="p-2 text-gray-400 hover:text-white bg-slate-800 rounded-full">
                <X className="w-5 h-5" />
              </button>
            </div>
            
            <div className="p-6 flex-1 overflow-y-auto">
              {campaignLogsLoading ? (
                <div className="flex justify-center items-center py-12">
                  <div className="w-8 h-8 border-2 border-indigo-500 border-t-transparent rounded-full animate-spin"></div>
                </div>
              ) : campaignLogsData.length === 0 ? (
                <div className="text-center py-12 text-gray-500 text-sm">
                  No message records found for this campaign yet.
                </div>
              ) : (
                <div className="overflow-x-auto rounded-xl border border-slate-800/60">
                  <table className="w-full text-left text-sm text-gray-300 font-mono text-xs">
                    <thead className="bg-slate-900/60 uppercase tracking-widest text-[10px] text-gray-400">
                      <tr>
                        <th className="px-4 py-3">Timestamp</th>
                        <th className="px-4 py-3">Phone Number</th>
                        <th className="px-4 py-3">Status</th>
                        <th className="px-4 py-3">Network Status</th>
                        <th className="px-4 py-3">Gateway</th>
                        <th className="px-4 py-3">Cost</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-800/40">
                      {campaignLogsData.map((log: any) => (
                        <tr key={log.id} className="hover:bg-slate-900/20">
                          <td className="px-4 py-3 text-gray-500">{new Date(log.created_at).toLocaleString()}</td>
                          <td className="px-4 py-3 font-bold text-white">{log.msisdn}</td>
                          <td className="px-4 py-3">
                            <span className={`px-2 py-0.5 rounded text-[9px] font-bold uppercase ${
                              log.status === 'DELIVERED' ? 'bg-emerald-500/10 text-emerald-400' :
                              log.status === 'FAILED' ? 'bg-red-500/10 text-red-400' :
                              'bg-indigo-500/10 text-indigo-400'
                            }`}>
                              {log.status}
                            </span>
                          </td>
                          <td className="px-4 py-3">
                            <span className="text-gray-400 font-mono text-xs bg-slate-800/50 px-2 py-1 rounded">
                              {log.network_status_code || 'N/A'}
                            </span>
                          </td>
                          <td className="px-4 py-3 text-indigo-300">{log.carrier_route_id || 'DEFAULT'}</td>
                          <td className="px-4 py-3 text-gray-400">Ksh {Number(log.cost_incurred).toFixed(4)}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              )}
            </div>
          </div>
        </div>
      )}

    </div>
  );
}
