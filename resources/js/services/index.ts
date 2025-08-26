// API client
export { api, apiClient, ApiError, type ApiResponse, type PaginatedResponse } from './api';

// Services
export { dashboardService } from './dashboard';
export { tradesService } from './trades';
export { exchangesService } from './exchanges';
export { analysisService } from './analysis';

// Types for service methods
export type { DashboardOverviewParams, DashboardMetricsParams } from './dashboard';
export type { 
    TradeWithAnalysis, 
    PnlChartParams, 
    PnlChartData 
} from './trades';
export type { 
    ExchangeConnectionRequest, 
    TestConnectionRequest, 
    TestConnectionResponse,
    SyncStatus,
    SyncStats 
} from './exchanges';
export type { 
    AnalysisFilters, 
    MarketDataCollectionRequest, 
    TradeAnalysisReport 
} from './analysis';