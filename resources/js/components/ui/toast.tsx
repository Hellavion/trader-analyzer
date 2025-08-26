import { type Toast, useToast } from '@/hooks/use-toast';
import { Icon } from '@/components/ui/icon';
import { Button } from '@/components/ui/button';
import { AlertCircle, CheckCircle, Info, X, XCircle } from 'lucide-react';
import { createPortal } from 'react-dom';

const toastIcons = {
    success: CheckCircle,
    error: XCircle,
    warning: AlertCircle,
    info: Info,
} as const;

const toastStyles = {
    success: 'border-green-200 bg-green-50 text-green-900 dark:border-green-800 dark:bg-green-950 dark:text-green-100',
    error: 'border-red-200 bg-red-50 text-red-900 dark:border-red-800 dark:bg-red-950 dark:text-red-100',
    warning: 'border-yellow-200 bg-yellow-50 text-yellow-900 dark:border-yellow-800 dark:bg-yellow-950 dark:text-yellow-100',
    info: 'border-blue-200 bg-blue-50 text-blue-900 dark:border-blue-800 dark:bg-blue-950 dark:text-blue-100',
} as const;

const iconStyles = {
    success: 'text-green-600 dark:text-green-400',
    error: 'text-red-600 dark:text-red-400',
    warning: 'text-yellow-600 dark:text-yellow-400',
    info: 'text-blue-600 dark:text-blue-400',
} as const;

function ToastItem({ toast }: { toast: Toast }) {
    const { removeToast } = useToast();
    const IconComponent = toastIcons[toast.type];

    return (
        <div
            className={`
                relative flex items-start gap-3 rounded-lg border p-4 shadow-lg transition-all duration-300 ease-in-out
                animate-in slide-in-from-right-full
                ${toastStyles[toast.type]}
            `}
        >
            <Icon iconNode={IconComponent} className={`mt-0.5 h-5 w-5 flex-shrink-0 ${iconStyles[toast.type]}`} />
            
            <div className="flex-1 space-y-1">
                <h4 className="text-sm font-semibold">{toast.title}</h4>
                {toast.description && (
                    <p className="text-sm opacity-90">{toast.description}</p>
                )}
            </div>

            <Button
                variant="ghost"
                size="sm"
                className="h-6 w-6 p-0 opacity-60 hover:opacity-100"
                onClick={() => removeToast(toast.id)}
            >
                <X className="h-4 w-4" />
                <span className="sr-only">Close</span>
            </Button>
        </div>
    );
}

export function ToastContainer() {
    const { toasts } = useToast();

    if (toasts.length === 0) return null;

    return createPortal(
        <div className="fixed bottom-0 right-0 z-[100] flex max-h-screen w-full flex-col-reverse p-4 sm:bottom-4 sm:right-4 sm:top-auto sm:flex-col md:max-w-[420px]">
            {toasts.map((toast) => (
                <ToastItem key={toast.id} toast={toast} />
            ))}
        </div>,
        document.body
    );
}