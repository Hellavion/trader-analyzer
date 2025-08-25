import { type RouteDefinition } from '../wayfinder';

export const exchangesIndex = (): RouteDefinition<'get'> => ({
    url: '/exchanges',
    method: 'get',
});

export const tradesIndex = (): RouteDefinition<'get'> => ({
    url: '/trades',
    method: 'get',
});

export const analysisIndex = (): RouteDefinition<'get'> => ({
    url: '/analysis',
    method: 'get',
});