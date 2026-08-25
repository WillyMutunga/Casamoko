import axios from 'axios';

// Determine dynamic base API URL matching current browser origin to prevent CORS www/non-www mismatch
const getBaseUrl = () => {
  if (typeof window !== 'undefined' && window.location.hostname.includes('casamoko.co.ke')) {
    return window.location.origin + '/api';
  }
  return import.meta.env.VITE_API_URL || 'http://127.0.0.1:8000/api';
};

// Create standardized Axios instance
const apiClient = axios.create({
  baseURL: getBaseUrl(),
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
});

// Auto-attach token to all requests if it exists in sessionStorage
apiClient.interceptors.request.use((config) => {
  const token = sessionStorage.getItem('casamoko_session_token');
  if (token && config.headers) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
}, (error) => {
  return Promise.reject(error);
});

export default apiClient;
