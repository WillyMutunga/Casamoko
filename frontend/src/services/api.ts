import axios from 'axios';

// Create standardized Axios instance
const apiClient = axios.create({
  baseURL: import.meta.env.VITE_API_URL || 'http://127.0.0.1:8000/api',
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
