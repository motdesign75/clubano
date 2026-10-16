import * as SecureStore from "expo-secure-store";
import { API_BASE_URL } from "./config";

const TOKEN_KEY = "clubano_mobile_token";

export class ApiError extends Error {
  constructor(message: string, public readonly status: number) {
    super(message);
  }
}

export async function saveToken(token: string) {
  await SecureStore.setItemAsync(TOKEN_KEY, token);
}

export async function readToken() {
  return SecureStore.getItemAsync(TOKEN_KEY);
}

export async function clearToken() {
  await SecureStore.deleteItemAsync(TOKEN_KEY).catch(() => null);
}

export async function apiFetch<T>(path: string, options: RequestInit = {}): Promise<T> {
  const token = await readToken();
  const response = await fetch(`${API_BASE_URL}${path}`, {
    ...options,
    headers: {
      Accept: "application/json",
      "Content-Type": "application/json",
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
      ...(options.headers ?? {})
    }
  }).catch(() => {
    throw new ApiError("Keine Verbindung zu Clubano. Bitte prüfe deine Internetverbindung und versuche es erneut.", 0);
  });

  const payload: unknown = await response.json().catch(() => ({}));

  if (!response.ok) {
    const validationMessages = response.status === 422 && payload && typeof payload === "object" && "errors" in payload
      && payload.errors && typeof payload.errors === "object"
      ? Object.values(payload.errors).flat().filter((value): value is string => typeof value === "string").join("\n") : "";
    const message = response.status >= 500
      ? "Clubano ist vorübergehend nicht erreichbar. Bitte versuche es später erneut."
      : response.status === 429
      ? "Zu viele Versuche. Bitte warte einen Moment und versuche es erneut."
      : response.status === 401
      ? "Deine Sitzung ist abgelaufen. Bitte melde dich erneut an."
      : validationMessages
      ? validationMessages
      : payload && typeof payload === "object" && "message" in payload && typeof payload.message === "string"
      ? payload.message
      : "Die Anfrage konnte nicht verarbeitet werden.";
    throw new ApiError(message, response.status);
  }

  return payload as T;
}
