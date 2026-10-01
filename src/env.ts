import { defineEnvVars } from '@sveltejs/kit/env';

const optional = (input: string | undefined) => input;

export const variables = defineEnvVars({
  DATABASE_URL: { description: 'PostgreSQL connection string', schema: optional },
  OIDC_ISSUER_URL: { description: 'OIDC issuer URL', schema: optional },
  OIDC_CLIENT_ID: { description: 'OIDC client ID', schema: optional },
  OIDC_CLIENT_SECRET: { description: 'OIDC client secret', schema: optional },
  OIDC_APP_URL: { description: 'Public app origin (no path, query, or fragment)', schema: optional }
});
