import { StatusBar } from "expo-status-bar";
import React, { useEffect, useMemo, useState } from "react";
import {
  ActivityIndicator,
  Alert,
  Button,
  FlatList,
  Pressable,
  SafeAreaView,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  View
} from "react-native";
import { apiFetch, clearToken, readToken, saveToken } from "./src/api";
import type { ClubEvent, DocumentItem, Member, Shift, Tenant } from "./src/types";

type Screen = "home" | "profile" | "events" | "shifts" | "documents" | "news" | "contact";

type Session = {
  tenant: Tenant;
  member: Member;
};

export default function App() {
  const [session, setSession] = useState<Session | null>(null);
  const [loading, setLoading] = useState(true);
  const [screen, setScreen] = useState<Screen>("home");

  async function loadSession() {
    try {
      const token = await readToken();
      if (!token) {
        setSession(null);
        return;
      }
      const data = await apiFetch<Session>("/api/mobile/me");
      setSession(data);
    } catch {
      await clearToken();
      setSession(null);
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    loadSession();
  }, []);

  if (loading) {
    return <Centered><ActivityIndicator /></Centered>;
  }

  if (!session) {
    return <LoginScreen onLogin={async (nextSession) => {
      setSession(nextSession);
      setScreen("home");
    }} />;
  }

  return (
    <SafeAreaView style={styles.shell}>
      <StatusBar style="dark" />
      <View style={styles.header}>
        <View>
          <Text style={styles.kicker}>{session.tenant.name}</Text>
          <Text style={styles.title}>Clubano</Text>
        </View>
        <Pressable onPress={async () => {
          await apiFetch("/api/mobile/logout", { method: "POST" }).catch(() => null);
          await clearToken();
          setSession(null);
        }}>
          <Text style={styles.link}>Abmelden</Text>
        </Pressable>
      </View>

      <View style={styles.tabs}>
        {([
          ["home", "Start"],
          ["profile", "Daten"],
          ["events", "Termine"],
          ["shifts", "Dienste"],
          ["documents", "Satzung"],
          ["news", "News"],
          ["contact", "Kontakt"]
        ] as Array<[Screen, string]>).map(([key, label]) => (
          <Pressable key={key} onPress={() => setScreen(key)} style={[styles.tab, screen === key && styles.tabActive]}>
            <Text style={[styles.tabText, screen === key && styles.tabTextActive]}>{label}</Text>
          </Pressable>
        ))}
      </View>

      {screen === "home" && <Home session={session} setScreen={setScreen} />}
      {screen === "profile" && <Profile member={session.member} refresh={loadSession} />}
      {screen === "events" && <Events />}
      {screen === "shifts" && <Shifts />}
      {screen === "documents" && <Documents />}
      {screen === "news" && <News />}
      {screen === "contact" && <Contact />}
    </SafeAreaView>
  );
}

function LoginScreen({ onLogin }: { onLogin: (session: Session) => void }) {
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [busy, setBusy] = useState(false);

  async function login() {
    try {
      setBusy(true);
      const data = await apiFetch<Session & { token: string }>("/api/mobile/login", {
        method: "POST",
        body: JSON.stringify({ email, password, device_name: "Clubano App" })
      });
      await saveToken(data.token);
      onLogin(data);
    } catch (error) {
      Alert.alert("Login nicht möglich", error instanceof Error ? error.message : "Bitte prüfe die Eingaben.");
    } finally {
      setBusy(false);
    }
  }

  return (
    <SafeAreaView style={styles.shell}>
      <View style={styles.login}>
        <Text style={styles.title}>Clubano</Text>
        <Text style={styles.muted}>Mit deinem Vereinszugang anmelden.</Text>
        <TextInput style={styles.input} autoCapitalize="none" keyboardType="email-address" placeholder="E-Mail" value={email} onChangeText={setEmail} />
        <TextInput style={styles.input} secureTextEntry placeholder="Passwort" value={password} onChangeText={setPassword} />
        <Button title={busy ? "Bitte warten..." : "Anmelden"} onPress={login} disabled={busy || !email || !password} />
      </View>
    </SafeAreaView>
  );
}

function Home({ session, setScreen }: { session: Session; setScreen: (screen: Screen) => void }) {
  return (
    <ScrollView contentContainerStyle={styles.content}>
      <Text style={styles.heading}>Hallo {session.member.first_name ?? session.member.full_name}</Text>
      <Text style={styles.muted}>Hier findest du die wichtigsten Vereinsfunktionen ohne Chat und ohne Rechnungen.</Text>
      {[
        ["profile", "Stammdaten prüfen"],
        ["events", "Veranstaltungen"],
        ["shifts", "Dienstplan"],
        ["documents", "Satzung & Beitragsordnung"],
        ["contact", "Kontakt zum Verein"]
      ].map(([target, label]) => (
        <Pressable key={target} style={styles.card} onPress={() => setScreen(target as Screen)}>
          <Text style={styles.cardTitle}>{label}</Text>
        </Pressable>
      ))}
    </ScrollView>
  );
}

function Profile({ member, refresh }: { member: Member; refresh: () => void }) {
  const initial = useMemo(() => ({
    first_name: member.first_name ?? "",
    last_name: member.last_name ?? "",
    organization: member.organization ?? "",
    email: member.email ?? "",
    mobile: member.mobile ?? "",
    landline: member.landline ?? "",
    street: member.street ?? "",
    address_addition: member.address_addition ?? "",
    zip: member.zip ?? "",
    city: member.city ?? "",
    country: member.country ?? "",
    change_note: ""
  }), [member]);
  const [form, setForm] = useState(initial);

  async function submit() {
    try {
      await apiFetch("/api/mobile/me/profile-change", { method: "POST", body: JSON.stringify(form) });
      Alert.alert("Gesendet", "Deine Änderungen wurden zur Prüfung eingereicht.");
      refresh();
    } catch (error) {
      Alert.alert("Nicht gespeichert", error instanceof Error ? error.message : "Bitte versuche es erneut.");
    }
  }

  return (
    <ScrollView contentContainerStyle={styles.content}>
      <Text style={styles.heading}>Meine Stammdaten</Text>
      {Object.entries({
        first_name: "Vorname",
        last_name: "Nachname",
        organization: "Organisation",
        email: "E-Mail",
        mobile: "Mobil",
        landline: "Telefon",
        street: "Straße",
        address_addition: "Adresszusatz",
        zip: "PLZ",
        city: "Ort",
        country: "Land",
        change_note: "Hinweis"
      }).map(([key, label]) => (
        <View key={key} style={styles.field}>
          <Text style={styles.label}>{label}</Text>
          <TextInput style={styles.input} value={(form as any)[key]} onChangeText={(value) => setForm({ ...form, [key]: value })} />
        </View>
      ))}
      <Button title="Änderungen zur Prüfung senden" onPress={submit} />
    </ScrollView>
  );
}

function Events() {
  const [events, setEvents] = useState<ClubEvent[]>([]);
  useEffect(() => { apiFetch<{ events: ClubEvent[] }>("/api/mobile/events").then((data) => setEvents(data.events)); }, []);

  async function respond(event: ClubEvent, status: string) {
    await apiFetch(`/api/mobile/events/${event.id}/response`, { method: "POST", body: JSON.stringify({ status }) });
    const data = await apiFetch<{ events: ClubEvent[] }>("/api/mobile/events");
    setEvents(data.events);
  }

  return (
    <FlatList contentContainerStyle={styles.content} data={events} keyExtractor={(item) => String(item.id)} renderItem={({ item }) => (
      <View style={styles.card}>
        <Text style={styles.cardTitle}>{item.title}</Text>
        <Text style={styles.muted}>{formatDate(item.starts_at)} · {item.location ?? "Ort offen"}</Text>
        <Text style={styles.muted}>{item.price_label}</Text>
        <Text style={styles.badge}>{item.invitation?.label ?? "Keine Rückmeldung"}</Text>
        <View style={styles.actions}>
          <Button title="Zusage" onPress={() => respond(item, "accepted")} />
          <Button title="Vielleicht" onPress={() => respond(item, "maybe")} />
          <Button title="Absage" onPress={() => respond(item, "declined")} />
        </View>
      </View>
    )} />
  );
}

function Shifts() {
  const [events, setEvents] = useState<Array<{ id: number; title: string; shifts: Shift[] }>>([]);
  useEffect(() => { apiFetch<{ events: Array<{ id: number; title: string; shifts: Shift[] }> }>("/api/mobile/shifts").then((data) => setEvents(data.events)); }, []);

  return (
    <ScrollView contentContainerStyle={styles.content}>
      <Text style={styles.heading}>Dienstplan</Text>
      {events.length === 0 && <Text style={styles.muted}>Aktuell ist kein Dienstplan für die App freigegeben.</Text>}
      {events.map((event) => (
        <View key={event.id} style={styles.card}>
          <Text style={styles.cardTitle}>{event.title}</Text>
          {event.shifts.map((shift) => (
            <View key={shift.id} style={styles.shift}>
              <Text style={styles.label}>{shift.title}</Text>
              <Text style={styles.muted}>{formatDate(shift.starts_at)} · offen: {shift.open_slots}</Text>
              <Text>{shift.assignments.map((a) => a.is_me ? `${a.name} (du)` : a.name).join(", ") || "Noch niemand eingetragen"}</Text>
            </View>
          ))}
        </View>
      ))}
    </ScrollView>
  );
}

function Documents() {
  const [documents, setDocuments] = useState<DocumentItem[]>([]);
  useEffect(() => { apiFetch<{ documents: DocumentItem[] }>("/api/mobile/documents").then((data) => setDocuments(data.documents)); }, []);
  return (
    <FlatList contentContainerStyle={styles.content} data={documents} keyExtractor={(item) => String(item.id)} ListHeaderComponent={<Text style={styles.heading}>Satzung & Beitragsordnung</Text>} renderItem={({ item }) => (
      <View style={styles.card}>
        <Text style={styles.cardTitle}>{item.title}</Text>
        <Text style={styles.muted}>{item.description ?? "Freigegebenes Vereinsdokument"}</Text>
      </View>
    )} />
  );
}

function News() {
  return (
    <ScrollView contentContainerStyle={styles.content}>
      <Text style={styles.heading}>Vereinsnews</Text>
      <Text style={styles.muted}>Dieser Bereich ist für offizielle Mitteilungen des Vereins vorbereitet. Keine Kommentare, kein Chat.</Text>
    </ScrollView>
  );
}

function Contact() {
  const [contact, setContact] = useState<any>(null);
  useEffect(() => { apiFetch<{ contact: any }>("/api/mobile/contact").then((data) => setContact(data.contact)); }, []);
  return (
    <ScrollView contentContainerStyle={styles.content}>
      <Text style={styles.heading}>Kontakt zum Verein</Text>
      <View style={styles.card}>
        <Text style={styles.cardTitle}>{contact?.club_name ?? "Verein"}</Text>
        <Text>{contact?.email ?? "Keine E-Mail hinterlegt"}</Text>
        <Text>{contact?.phone ?? ""}</Text>
        <Text>{[contact?.address, contact?.zip, contact?.city].filter(Boolean).join(", ")}</Text>
      </View>
    </ScrollView>
  );
}

function Centered({ children }: { children: React.ReactNode }) {
  return <SafeAreaView style={styles.centered}>{children}</SafeAreaView>;
}

function formatDate(value: string) {
  return new Date(value).toLocaleString("de-DE", { dateStyle: "medium", timeStyle: "short" });
}

const styles = StyleSheet.create({
  shell: { flex: 1, backgroundColor: "#f8fafc" },
  centered: { flex: 1, alignItems: "center", justifyContent: "center" },
  login: { flex: 1, justifyContent: "center", padding: 24, gap: 14 },
  header: { padding: 18, flexDirection: "row", justifyContent: "space-between", alignItems: "center" },
  kicker: { color: "#64748b", fontSize: 12, fontWeight: "700", textTransform: "uppercase" },
  title: { fontSize: 28, fontWeight: "800", color: "#0f172a" },
  heading: { fontSize: 24, fontWeight: "800", color: "#0f172a", marginBottom: 8 },
  muted: { color: "#64748b", lineHeight: 20 },
  link: { color: "#4f46e5", fontWeight: "700" },
  tabs: { flexDirection: "row", flexWrap: "wrap", gap: 8, paddingHorizontal: 16, paddingBottom: 12 },
  tab: { borderWidth: 1, borderColor: "#cbd5e1", borderRadius: 999, paddingVertical: 8, paddingHorizontal: 12 },
  tabActive: { backgroundColor: "#0f172a", borderColor: "#0f172a" },
  tabText: { color: "#334155", fontWeight: "700" },
  tabTextActive: { color: "#fff" },
  content: { padding: 16, gap: 14 },
  card: { backgroundColor: "#fff", borderRadius: 16, padding: 16, borderWidth: 1, borderColor: "#e2e8f0", gap: 8 },
  cardTitle: { fontSize: 18, fontWeight: "800", color: "#0f172a" },
  field: { gap: 6 },
  label: { fontWeight: "700", color: "#334155" },
  input: { backgroundColor: "#fff", borderWidth: 1, borderColor: "#cbd5e1", borderRadius: 12, padding: 12 },
  badge: { alignSelf: "flex-start", backgroundColor: "#eef2ff", color: "#3730a3", borderRadius: 999, paddingVertical: 4, paddingHorizontal: 10, fontWeight: "700" },
  actions: { flexDirection: "row", flexWrap: "wrap", gap: 8 },
  shift: { borderTopWidth: 1, borderTopColor: "#e2e8f0", paddingTop: 10, gap: 4 }
});
