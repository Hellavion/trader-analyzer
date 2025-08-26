import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { PlaceholderPattern } from '@/components/ui/placeholder-pattern';
import AppLayout from '@/layouts/app-layout';
import { index as exchangesIndex } from '@/routes/exchanges';
import { type BreadcrumbItem, type UserExchange } from '@/types';
import { exchangeService } from '@/services/exchange';
import { Head, useForm } from '@inertiajs/react';
import { Plus, Settings, Trash2, Zap, RefreshCw } from 'lucide-react';
import { useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Биржи',
        href: exchangesIndex().url,
    },
];

interface ExchangeData {
    id: number;
    exchange: string;
    api_key: string;
    is_active: boolean;
    created_at: string;
    display_name: string;
}

interface Props {
    exchanges: ExchangeData[];
}

export default function ExchangesIndex({ exchanges = [] }: Props) {
    const [isAddingExchange, setIsAddingExchange] = useState(false);
    const [syncingExchange, setSyncingExchange] = useState<string | null>(null);
    
    const { data, setData, post, processing, errors, reset } = useForm({
        exchange: '',
        api_key: '',
        api_secret: '',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/exchanges', {
            onSuccess: () => {
                setIsAddingExchange(false);
                reset();
            },
        });
    };

    const handleDisconnect = (exchangeId: number) => {
        // TODO: Implement disconnect functionality
        console.log('Disconnect exchange:', exchangeId);
    };

    const handleSync = async (exchangeName: string) => {
        setSyncingExchange(exchangeName);
        try {
            const response = await exchangeService.sync(exchangeName);
            if (response.success) {
                // Показать уведомление об успехе
                alert('Синхронизация запущена успешно!');
                // Перезагрузить страницу через несколько секунд
                setTimeout(() => {
                    window.location.reload();
                }, 2000);
            } else {
                alert('Ошибка синхронизации: ' + response.message);
            }
        } catch (error) {
            alert('Ошибка синхронизации: ' + (error as Error).message);
        } finally {
            setSyncingExchange(null);
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Подключения к биржам" />
            
            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto p-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight">Подключения к биржам</h1>
                        <p className="text-muted-foreground">
                            Подключите ваши торговые аккаунты для анализа торговли
                        </p>
                    </div>
                    
                    <Dialog open={isAddingExchange} onOpenChange={setIsAddingExchange}>
                        <DialogTrigger asChild>
                            <Button className="flex items-center gap-2">
                                <Plus className="h-4 w-4" />
                                Добавить биржу
                            </Button>
                        </DialogTrigger>
                        <DialogContent className="sm:max-w-[425px]">
                            <DialogHeader>
                                <DialogTitle>Подключить биржу</DialogTitle>
                                <DialogDescription>
                                    Добавьте API ключи вашей биржи для синхронизации сделок. Мы запрашиваем только права на чтение.
                                </DialogDescription>
                            </DialogHeader>
                            
                            <form onSubmit={handleSubmit} className="space-y-4">
                                <div className="space-y-2">
                                    <Label htmlFor="exchange">Биржа</Label>
                                    <Select value={data.exchange} onValueChange={(value) => setData('exchange', value)}>
                                        <SelectTrigger>
                                            <SelectValue placeholder="Выберите биржу" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="bybit">Bybit</SelectItem>
                                            <SelectItem value="mexc">MEXC</SelectItem>
                                        </SelectContent>
                                    </Select>
                                    {errors.exchange && <p className="text-sm text-red-500">{errors.exchange}</p>}
                                </div>
                                
                                <div className="space-y-2">
                                    <Label htmlFor="api_key">API Ключ</Label>
                                    <Input
                                        id="api_key"
                                        type="text"
                                        value={data.api_key}
                                        onChange={(e) => setData('api_key', e.target.value)}
                                        placeholder="Ваш API ключ"
                                    />
                                    {errors.api_key && <p className="text-sm text-red-500">{errors.api_key}</p>}
                                </div>
                                
                                <div className="space-y-2">
                                    <Label htmlFor="api_secret">API Секрет</Label>
                                    <Input
                                        id="api_secret"
                                        type="password"
                                        value={data.api_secret}
                                        onChange={(e) => setData('api_secret', e.target.value)}
                                        placeholder="Ваш API секрет"
                                    />
                                    {errors.api_secret && <p className="text-sm text-red-500">{errors.api_secret}</p>}
                                </div>
                                
                                <div className="flex justify-end gap-2 pt-4">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={() => setIsAddingExchange(false)}
                                    >
                                        Отмена
                                    </Button>
                                    <Button type="submit" disabled={processing}>
                                        {processing ? 'Подключение...' : 'Подключить'}
                                    </Button>
                                </div>
                            </form>
                        </DialogContent>
                    </Dialog>
                </div>

                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                    {exchanges.length === 0 ? (
                        <div className="col-span-full">
                            <Card className="relative overflow-hidden">
                                <CardContent className="flex flex-col items-center justify-center py-16">
                                    <PlaceholderPattern className="absolute inset-0 size-full stroke-neutral-900/10 dark:stroke-neutral-100/10" />
                                    <div className="relative flex flex-col items-center text-center">
                                        <Settings className="h-12 w-12 text-muted-foreground mb-4" />
                                        <h3 className="text-lg font-semibold mb-2">Нет подключенных бирж</h3>
                                        <p className="text-muted-foreground mb-6 max-w-sm">
                                            Подключите первую биржу, чтобы начать анализировать ваши сделки и улучшить стратегию Smart Money.
                                        </p>
                                        <Button onClick={() => setIsAddingExchange(true)}>
                                            <Plus className="h-4 w-4 mr-2" />
                                            Подключить биржу
                                        </Button>
                                    </div>
                                </CardContent>
                            </Card>
                        </div>
                    ) : (
                        exchanges.map((exchange) => (
                            <Card key={exchange.id} className="relative">
                                <CardHeader className="pb-4">
                                    <div className="flex items-center justify-between">
                                        <CardTitle className="text-lg">{exchange.display_name}</CardTitle>
                                        <div className="flex items-center gap-2">
                                            {exchange.is_active ? (
                                                <div className="flex items-center gap-1 text-green-600">
                                                    <Zap className="h-4 w-4" />
                                                    <span className="text-sm font-medium">Активна</span>
                                                </div>
                                            ) : (
                                                <div className="flex items-center gap-1 text-gray-500">
                                                    <span className="text-sm font-medium">Неактивна</span>
                                                </div>
                                            )}
                                        </div>
                                    </div>
                                    <CardDescription>
                                        Подключена {new Date(exchange.created_at).toLocaleDateString('ru-RU')}
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="pb-4">
                                    <div className="space-y-2 text-sm">
                                        <div>
                                            <span className="text-muted-foreground">API Ключ:</span>
                                            <span className="ml-2 font-mono">
                                                {exchange.api_key}
                                            </span>
                                        </div>
                                        <div>
                                            <span className="text-muted-foreground">Статус:</span>
                                            <span className={`ml-2 font-medium ${exchange.is_active ? 'text-green-600' : 'text-gray-500'}`}>
                                                {exchange.is_active ? 'Подключена' : 'Отключена'}
                                            </span>
                                        </div>
                                    </div>
                                </CardContent>
                                <CardFooter className="pt-0">
                                    <div className="flex gap-2 w-full">
                                        <Button variant="outline" size="sm" className="flex-1">
                                            Тест соединения
                                        </Button>
                                        <Button 
                                            variant="outline" 
                                            size="sm" 
                                            onClick={() => handleDisconnect(exchange.id)}
                                            className="text-red-600 hover:text-red-700"
                                        >
                                            <Trash2 className="h-4 w-4" />
                                        </Button>
                                    </div>
                                </CardFooter>
                            </Card>
                        ))
                    )}
                </div>
                
                {exchanges.length > 0 && (
                    <div className="mt-8">
                        <Card>
                            <CardHeader>
                                <CardTitle>Статус синхронизации</CardTitle>
                                <CardDescription>
                                    Мониторинг синхронизации данных с биржи
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <div className="space-y-4">
                                    <div className="flex items-center justify-between p-3 bg-muted rounded-lg">
                                        <div>
                                            <p className="font-medium">Последняя синхронизация</p>
                                            <p className="text-sm text-muted-foreground">2 минуты назад</p>
                                        </div>
                                        <Button 
                                            variant="outline" 
                                            size="sm"
                                            onClick={() => handleSync('bybit')}
                                            disabled={syncingExchange === 'bybit'}
                                        >
                                            {syncingExchange === 'bybit' ? (
                                                <>
                                                    <RefreshCw className="h-4 w-4 mr-2 animate-spin" />
                                                    Синхронизация...
                                                </>
                                            ) : (
                                                <>
                                                    <RefreshCw className="h-4 w-4 mr-2" />
                                                    Синхронизировать
                                                </>
                                            )}
                                        </Button>
                                    </div>
                                    
                                    <div className="grid gap-4 md:grid-cols-3">
                                        <div className="text-center p-3 bg-muted rounded-lg">
                                            <p className="text-2xl font-bold">0</p>
                                            <p className="text-sm text-muted-foreground">Сделки синхронизированы</p>
                                        </div>
                                        <div className="text-center p-3 bg-muted rounded-lg">
                                            <p className="text-2xl font-bold">0</p>
                                            <p className="text-sm text-muted-foreground">Анализ создан</p>
                                        </div>
                                        <div className="text-center p-3 bg-muted rounded-lg">
                                            <p className="text-2xl font-bold">-</p>
                                            <p className="text-sm text-muted-foreground">Smart Money Оценка</p>
                                        </div>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}