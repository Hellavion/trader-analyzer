import axios, { type AxiosInstance, type AxiosRequestConfig, type AxiosResponse } from 'axios';

// Базовая конфигурация Axios
const createApiClient = (): AxiosInstance => {
    const client = axios.create({
        baseURL: '/api',
        timeout: 30000,
        withCredentials: true,
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
    });

    // Request interceptor для добавления CSRF токена
    client.interceptors.request.use(
        (config) => {
            const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            if (token) {
                config.headers['X-CSRF-TOKEN'] = token;
            }
            return config;
        },
        (error) => {
            return Promise.reject(error);
        }
    );

    // Response interceptor для обработки ошибок
    client.interceptors.response.use(
        (response: AxiosResponse) => {
            // Если ответ содержит поле success, проверяем его
            if (response.data && typeof response.data === 'object' && 'success' in response.data) {
                if (!response.data.success) {
                    throw new ApiError(
                        response.data.message || 'API request failed',
                        response.status,
                        response.data
                    );
                }
            }
            return response;
        },
        (error) => {
            if (error.response) {
                // Сервер ответил с ошибкой
                const message = error.response.data?.message || `HTTP ${error.response.status} Error`;
                throw new ApiError(message, error.response.status, error.response.data);
            } else if (error.request) {
                // Запрос был отправлен, но ответа нет
                throw new ApiError('Network error: No response from server', 0);
            } else {
                // Ошибка при настройке запроса
                throw new ApiError(error.message || 'Request setup error', 0);
            }
        }
    );

    return client;
};

// Кастомная ошибка API
export class ApiError extends Error {
    public status: number;
    public data?: any;

    constructor(message: string, status: number = 0, data?: any) {
        super(message);
        this.name = 'ApiError';
        this.status = status;
        this.data = data;
    }
}

// Создаем экземпляр API client
export const apiClient = createApiClient();

// Вспомогательные функции для различных HTTP методов
export const api = {
    get: <T = any>(url: string, config?: AxiosRequestConfig): Promise<T> =>
        apiClient.get(url, config).then(res => res.data),

    post: <T = any>(url: string, data?: any, config?: AxiosRequestConfig): Promise<T> =>
        apiClient.post(url, data, config).then(res => res.data),

    put: <T = any>(url: string, data?: any, config?: AxiosRequestConfig): Promise<T> =>
        apiClient.put(url, data, config).then(res => res.data),

    patch: <T = any>(url: string, data?: any, config?: AxiosRequestConfig): Promise<T> =>
        apiClient.patch(url, data, config).then(res => res.data),

    delete: <T = any>(url: string, config?: AxiosRequestConfig): Promise<T> =>
        apiClient.delete(url, config).then(res => res.data),
};

// Типы для стандартного ответа API
export interface ApiResponse<T = any> {
    success: boolean;
    data?: T;
    message?: string;
    errors?: Record<string, string[]>;
    meta?: Record<string, any>;
}

export interface PaginatedResponse<T = any> extends ApiResponse<T[]> {
    data: T[];
    meta: {
        current_page: number;
        per_page: number;
        total: number;
        last_page: number;
        from: number;
        to: number;
    };
    links?: {
        first: string;
        last: string;
        prev: string | null;
        next: string | null;
    };
}