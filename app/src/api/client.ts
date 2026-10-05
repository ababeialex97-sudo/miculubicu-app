import { useSession } from '@/store/session';

const BASE_URL = (process.env.EXPO_PUBLIC_API_URL ?? 'https://miculubicu.ro/wp-json/mlb/v1').replace(/\/+$/, '');

export class ApiError extends Error {
  constructor(
    message: string,
    readonly status: number,
    readonly code: string,
  ) {
    super(message);
    this.name = 'ApiError';
  }
}

type RequestOptions = {
  method?: 'GET' | 'POST' | 'PATCH' | 'DELETE';
  body?: unknown;
  auth?: boolean;
};

export async function api<T>(path: string, { method = 'GET', body, auth = false }: RequestOptions = {}): Promise<T> {
  const headers: Record<string, string> = { Accept: 'application/json' };
  if (body !== undefined) {
    headers['Content-Type'] = 'application/json';
  }

  if (auth) {
    const token = useSession.getState().token;
    if (!token) {
      throw new ApiError('Trebuie să fii autentificat.', 401, 'mlb_unauthorized');
    }
    headers.Authorization = `Bearer ${token}`;
  }

  let response: Response;
  try {
    response = await fetch(`${BASE_URL}${path}`, {
      method,
      headers,
      body: body === undefined ? undefined : JSON.stringify(body),
    });
  } catch {
    throw new ApiError('Nu ne putem conecta. Verifică internetul și încearcă din nou.', 0, 'network');
  }

  const data: unknown = await response.json().catch(() => null);

  if (!response.ok) {
    const error = (data ?? {}) as { message?: string; code?: string };
    if (response.status === 401 && auth) {
      // Token expired or revoked (e.g. password changed): drop the session.
      useSession.getState().signOut();
    }
    throw new ApiError(error.message ?? 'A apărut o eroare. Încearcă din nou.', response.status, error.code ?? 'unknown');
  }

  return data as T;
}
