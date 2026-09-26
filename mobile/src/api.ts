import * as SecureStore from "expo-secure-store";
import { API_BASE_URL } from "./config";

const TOKEN_KEY = "clubano_mobile_token";

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
  });

  const payload: unknown = await response.json().catch(() => ({}));

  if (!response.ok) {
    const message = payload && typeof payload === "object" && "message" in payload && typeof payload.message === "string"
      ? payload.message
      : "Die Anfrage konnte nicht verarbeitet werden.";
    throw new Error(message);
  }

  return payload as T;
}
