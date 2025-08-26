import { useState, useCallback } from 'react';
import { ApiError } from '@/services/api';
import { useToast } from './use-toast';

interface UseApiState<T> {
    data: T | null;
    loading: boolean;
    error: ApiError | null;
}

interface UseApiOptions {
    showSuccessToast?: boolean;
    showErrorToast?: boolean;
    successMessage?: string;
    onSuccess?: (data: any) => void;
    onError?: (error: ApiError) => void;
}

export function useApi<T = any>(options: UseApiOptions = {}) {
    const {
        showSuccessToast = false,
        showErrorToast = true,
        successMessage,
        onSuccess,
        onError,
    } = options;

    const { addToast } = useToast();
    const [state, setState] = useState<UseApiState<T>>({
        data: null,
        loading: false,
        error: null,
    });

    const execute = useCallback(async (apiCall: () => Promise<T>) => {
        setState(prev => ({ ...prev, loading: true, error: null }));

        try {
            const result = await apiCall();
            setState(prev => ({ ...prev, data: result, loading: false }));

            if (showSuccessToast) {
                addToast({
                    type: 'success',
                    title: 'Success',
                    description: successMessage || 'Operation completed successfully',
                });
            }

            onSuccess?.(result);
            return result;
        } catch (error) {
            const apiError = error instanceof ApiError ? error : new ApiError(
                error instanceof Error ? error.message : 'An unexpected error occurred'
            );

            setState(prev => ({ ...prev, error: apiError, loading: false }));

            if (showErrorToast) {
                addToast({
                    type: 'error',
                    title: 'Error',
                    description: getErrorMessage(apiError),
                });
            }

            onError?.(apiError);
            throw apiError;
        }
    }, [showSuccessToast, showErrorToast, successMessage, onSuccess, onError, addToast]);

    const reset = useCallback(() => {
        setState({ data: null, loading: false, error: null });
    }, []);

    return {
        ...state,
        execute,
        reset,
    };
}

function getErrorMessage(error: ApiError): string {
    // Handle validation errors
    if (error.status === 422 && error.data?.errors) {
        const firstError = Object.values(error.data.errors)[0];
        if (Array.isArray(firstError) && firstError.length > 0) {
            return firstError[0];
        }
    }

    // Handle authentication errors
    if (error.status === 401) {
        return 'Authentication required. Please log in.';
    }

    // Handle authorization errors
    if (error.status === 403) {
        return 'You do not have permission to perform this action.';
    }

    // Handle not found errors
    if (error.status === 404) {
        return 'The requested resource was not found.';
    }

    // Handle server errors
    if (error.status >= 500) {
        return 'A server error occurred. Please try again later.';
    }

    // Handle network errors
    if (error.status === 0) {
        return 'Network error. Please check your internet connection.';
    }

    // Return the original message if available
    return error.message || 'An unexpected error occurred.';
}

// Специализированный хук для операций с мутацией (POST, PUT, DELETE)
export function useMutation<T = any>(options: UseApiOptions = {}) {
    return useApi<T>({
        showSuccessToast: true,
        ...options,
    });
}

// Специализированный хук для операций чтения (GET)
export function useQuery<T = any>(options: UseApiOptions = {}) {
    return useApi<T>({
        showErrorToast: true,
        ...options,
    });
}