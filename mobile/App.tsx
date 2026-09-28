import { StatusBar } from "expo-status-bar";
import { Ionicons } from "@expo/vector-icons";
import * as Notifications from "expo-notifications";
import React, { useEffect, useMemo, useState } from "react";
import {
  ActivityIndicator,
  Alert,
  FlatList,
  Image,
  Linking,
  Platform,
  Pressable,
  SafeAreaView,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  View
} from "react-native";
import { apiFetch, clearToken, readToken, saveToken } from "./src/api";
import type { AppNotification, ClubEvent, DocumentItem, Member, MemberCard, NewsItem, Shift, Tenant } from "./src/types";

type Screen = "home" | "notifications" | "card" | "profile" | "events" | "shifts" | "documents" | "news" | "contact" | "legal";
type IconName = keyof typeof Ionicons.glyphMap;

type Session = {
  tenant: Tenant;
  member: Member;
};

Notifications.setNotificationHandler({
  handleNotification: async () => ({
    shouldShowAlert: true,
    shouldPlaySound: true,
    shouldSetBadge: false
  })
});

function isRecord(value: unknown): value is Record<string, unknown> {
  return Boolean(value) && typeof value === "object" && !Array.isArray(value);
}

function valueOrEmpty(value: unknown) {
  return typeof value === "string" ? value : "";
}

function normalizeSession(value: unknown): Session | null {
  if (!isRecord(value) || !isRecord(value.tenant) || !isRecord(value.member)) {
    return null;
  }

  const tenant = {
    ...value.tenant,
    name: valueOrEmpty(value.tenant.name) || "Mein Clubano"
  } as Tenant;
  const member = {
    ...value.member,
    full_name: valueOrEmpty(value.member.full_name) || "Mitglied"
  } as Member;

  return { tenant, member };
}

function requireSession(value: unknown): Session {
  const session = normalizeSession(value);
  if (!session) {
    throw new Error("Die Sitzung konnte nicht geladen werden. Bitte melde dich erneut an.");
  }
  return session;
}

function arrayOrEmpty<T>(value: T[] | null | undefined) {
  return Array.isArray(value) ? value : [];
}

const navItems: Array<[Screen, string, IconName]> = [
  ["home", "Start", "home-outline"],
  ["notifications", "Mitteilungen", "notifications-outline"],
  ["card", "Ausweis", "qr-code-outline"],
  ["profile", "Daten", "person-circle-outline"],
  ["events", "Termine", "calendar-outline"],
  ["shifts", "Dienste", "people-outline"],
  ["documents", "Satzung", "document-text-outline"],
  ["news", "News", "megaphone-outline"],
  ["contact", "Kontakt", "mail-outline"],
  ["legal", "Rechtliches", "shield-checkmark-outline"]
];

const tileTones = {
  blue: { bg: "#EAF4FF", icon: "#1D4ED8", accent: "#BBD7FF" },
  green: { bg: "#EAF8EF", icon: "#047857", accent: "#BFE8CC" },
  amber: { bg: "#FFF5D8", icon: "#B45309", accent: "#F7D88B" },
  indigo: { bg: "#EEF2FF", icon: "#4338CA", accent: "#C7D2FE" },
  rose: { bg: "#FFF1F2", icon: "#BE123C", accent: "#FDA4AF" }
};

const homeTiles: Array<{ screen: Screen; title: string; text: string; icon: IconName; tone: keyof typeof tileTones }> = [
  { screen: "notifications", title: "Mitteilungen", text: "News und Hinweise auf einen Blick", icon: "notifications-outline", tone: "amber" },
  { screen: "card", title: "Ausweis", text: "Mitgliedskarte mit sicherem QR-Code", icon: "qr-code-outline", tone: "blue" },
  { screen: "profile", title: "Stammdaten", text: "Daten prüfen und Änderungen einreichen", icon: "person-outline", tone: "blue" },
  { screen: "events", title: "Termine", text: "Veranstaltungen und Rückmeldungen", icon: "calendar-clear-outline", tone: "green" },
  { screen: "shifts", title: "Dienstplan", text: "Freigegebene Dienste im Blick behalten", icon: "people-outline", tone: "amber" },
  { screen: "news", title: "News", text: "Wichtige Mitteilungen sofort lesen", icon: "megaphone-outline", tone: "rose" },
  { screen: "documents", title: "Dokumente", text: "Satzung und Beitragsordnung", icon: "reader-outline", tone: "indigo" },
  { screen: "contact", title: "Kontakt", text: "Direkter Draht zum Verein", icon: "mail-outline", tone: "rose" },
  { screen: "legal", title: "Rechtliches", text: "Datenschutz und App-Support", icon: "shield-checkmark-outline", tone: "indigo" }
];

export default function App() {
  const [session, setSession] = useState<Session | null>(null);
  const [loading, setLoading] = useState(true);
  const [screen, setScreen] = useState<Screen>("home");
  const [pushStatus, setPushStatus] = useState<string | null>(null);

  async function loadSession() {
    try {
      const token = await readToken();
      if (!token) {
        setSession(null);
        return;
      }
      const data = await apiFetch<unknown>("/api/mobile/me");
      setSession(requireSession(data));
    } catch {
      await clearToken();
      setSession(null);
    } finally {
      setLoading(false);
    }
  }

  async function registerPushToken() {
    try {
      if (Platform.OS === "android") {
        await Notifications.setNotificationChannelAsync("default", {
          name: "Mein Clubano",
          importance: Notifications.AndroidImportance.DEFAULT
        });
      }

      const current = await Notifications.getPermissionsAsync();
      const permission = current.granted ? current : await Notifications.requestPermissionsAsync();

      if (!permission.granted) {
        return;
      }

      const registered: string[] = [];
      const errors: string[] = [];

      try {
        const token = await Notifications.getExpoPushTokenAsync();
        await registerToken(token.data, "expo");
        registered.push("Expo");
      } catch (error) {
        errors.push(error instanceof Error ? error.message : "Expo-Token konnte nicht erzeugt werden.");
      }

      try {
        const token = await Notifications.getDevicePushTokenAsync();
        const nativeToken = typeof token.data === "string" ? token.data : String(token.data);
        await registerToken(nativeToken, Platform.OS === "ios" ? "apns" : "fcm");
        registered.push(Platform.OS === "ios" ? "APNs" : "FCM");
      } catch (error) {
        errors.push(error instanceof Error ? error.message : "Geräte-Token konnte nicht erzeugt werden.");
      }

      if (registered.length === 0) {
        throw new Error(errors.join(" / "));
      }

      setPushStatus(`Push vorbereitet: ${registered.join(", ")}`);
    } catch (error) {
      const message = error instanceof Error ? error.message : "Push konnte nicht eingerichtet werden.";
      setPushStatus(`Push-Hinweis: ${message}`);
      console.warn("Push registration failed", error);
    }
  }

  async function registerToken(token: string, provider: "expo" | "apns" | "fcm") {
    await apiFetch("/api/mobile/push-token", {
      method: "POST",
      body: JSON.stringify({
        token,
        provider,
        platform: Platform.OS,
        device_name: "Mein Clubano App"
      })
    });
  }

  useEffect(() => {
    loadSession();
  }, []);

  useEffect(() => {
    if (session) {
      registerPushToken();
    }
  }, [session?.member.id]);

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
        <View style={styles.brandRow}>
          <View style={styles.appMark}>
            <Ionicons name="people" size={20} color="#ffffff" />
          </View>
          <View>
            <Text style={styles.kicker}>{session.tenant.name}</Text>
            <Text style={styles.title}>Mein Clubano</Text>
          </View>
        </View>
        <Pressable onPress={async () => {
          await apiFetch("/api/mobile/logout", { method: "POST" }).catch(() => null);
          await clearToken();
          setSession(null);
        }} style={styles.iconButton}>
          <Ionicons name="log-out-outline" size={20} color="#1E3A8A" />
        </Pressable>
      </View>

      <View style={styles.tabs}>
        {navItems.map(([key, label, icon]) => (
          <Pressable key={key} onPress={() => setScreen(key)} style={[styles.tab, screen === key && styles.tabActive]}>
            <Ionicons name={icon} size={16} color={screen === key ? "#ffffff" : "#52708F"} />
            <Text style={[styles.tabText, screen === key && styles.tabTextActive]}>{label}</Text>
          </Pressable>
        ))}
      </View>

      {screen === "home" && <Home session={session} pushStatus={pushStatus} setScreen={setScreen} />}
      {screen === "notifications" && <NotificationsScreen />}
      {screen === "card" && <MemberCardScreen />}
      {screen === "profile" && <Profile member={session.member} refresh={loadSession} />}
      {screen === "events" && <Events />}
      {screen === "shifts" && <Shifts />}
      {screen === "documents" && <Documents />}
      {screen === "news" && <News />}
      {screen === "contact" && <Contact />}
      {screen === "legal" && <Legal />}
    </SafeAreaView>
  );
}

function LoginScreen({ onLogin }: { onLogin: (session: Session) => void }) {
  const [username, setUsername] = useState("");
  const [password, setPassword] = useState("");
  const [busy, setBusy] = useState(false);

  async function login() {
    try {
      setBusy(true);
      const data = await apiFetch<unknown>("/api/mobile/login", {
        method: "POST",
        body: JSON.stringify({ username, password, device_name: "Mein Clubano App" })
      });
      if (!isRecord(data) || typeof data.token !== "string") {
        throw new Error("Die Anmeldung wurde vom Server unvollständig beantwortet.");
      }
      const nextSession = requireSession(data);
      await saveToken(data.token);
      onLogin(nextSession);
    } catch (error) {
      Alert.alert("Login nicht möglich", error instanceof Error ? error.message : "Bitte prüfe die Eingaben.");
    } finally {
      setBusy(false);
    }
  }

  return (
    <SafeAreaView style={styles.shell}>
      <View style={styles.login}>
        <View style={styles.loginHero}>
          <View style={styles.loginMark}>
            <Ionicons name="people" size={34} color="#ffffff" />
          </View>
          <Text style={styles.loginTitle}>Mein Clubano</Text>
          <Text style={styles.loginText}>Dein Verein, Termine und wichtige Informationen an einem ruhigen Ort.</Text>
        </View>
        <View style={styles.loginPanel}>
          <Text style={styles.panelTitle}>Willkommen zurück</Text>
          <Text style={styles.muted}>Mit deinem App-Zugang anmelden.</Text>
          <TextInput style={styles.input} autoCapitalize="none" autoCorrect={false} placeholder="Benutzername" placeholderTextColor="#94A3B8" value={username} onChangeText={setUsername} />
          <TextInput style={styles.input} secureTextEntry placeholder="Passwort" placeholderTextColor="#94A3B8" value={password} onChangeText={setPassword} />
          <PrimaryButton label={busy ? "Bitte warten..." : "Anmelden"} icon="arrow-forward" onPress={login} disabled={busy || !username || !password} />
        </View>
      </View>
    </SafeAreaView>
  );
}

function Home({ session, pushStatus, setScreen }: { session: Session; pushStatus: string | null; setScreen: (screen: Screen) => void }) {
  return (
    <ScrollView contentContainerStyle={styles.content}>
      <View style={styles.welcomeCard}>
        <Text style={styles.welcomeKicker}>Schön, dass du da bist</Text>
        <Text style={styles.welcomeTitle}>Hallo {session.member.first_name || session.member.full_name || "Mitglied"}</Text>
        <Text style={styles.welcomeText}>Hier findest du die wichtigsten Vereinsfunktionen ohne Chat und ohne Rechnungen.</Text>
      </View>
      {pushStatus ? (
        <View style={styles.surfaceCard}>
          <InfoRow icon="notifications-outline" text={pushStatus} />
        </View>
      ) : null}
      <View style={styles.tileGrid}>
        {homeTiles.map((tile) => (
          <Pressable key={tile.screen} style={[styles.tile, { backgroundColor: tileTones[tile.tone].bg }]} onPress={() => setScreen(tile.screen)}>
            <View style={[styles.tileIcon, { backgroundColor: tileTones[tile.tone].accent }]}>
              <Ionicons name={tile.icon} size={22} color={tileTones[tile.tone].icon} />
            </View>
            <Text style={styles.tileTitle}>{tile.title}</Text>
            <Text style={styles.tileText}>{tile.text}</Text>
            <Ionicons name="chevron-forward" size={18} color={tileTones[tile.tone].icon} />
        </Pressable>
      ))}
      </View>
    </ScrollView>
  );
}

function NotificationsScreen() {
  const [items, setItems] = useState<AppNotification[]>([]);

  async function load() {
    try {
      const data = await apiFetch<{ notifications?: AppNotification[] }>("/api/mobile/notifications");
      setItems(arrayOrEmpty(data.notifications));
    } catch {
      setItems([]);
    }
  }

  useEffect(() => {
    load();
  }, []);

  async function markRead(notification: AppNotification) {
    try {
      await apiFetch(`/api/mobile/notifications/${notification.id}/read`, { method: "POST" });
      await load();
    } catch {
      Alert.alert("Nicht gespeichert", "Die Mitteilung konnte nicht als gelesen markiert werden.");
    }
  }

  async function markAllRead() {
    try {
      await apiFetch("/api/mobile/notifications/read-all", { method: "POST" });
      await load();
    } catch {
      Alert.alert("Nicht gespeichert", "Die Mitteilungen konnten nicht aktualisiert werden.");
    }
  }

  const unreadCount = items.filter((item) => !item.read_at).length;

  return (
    <FlatList
      contentContainerStyle={styles.content}
      data={items}
      keyExtractor={(item) => String(item.id)}
      ListHeaderComponent={(
        <View style={styles.notificationHeader}>
          <SectionHeader icon="notifications-outline" title="Mitteilungen" text="Wichtige Hinweise deines Vereins an einem Ort." />
          {unreadCount > 0 && <PrimaryButton label={`${unreadCount} als gelesen markieren`} icon="checkmark-done" onPress={markAllRead} />}
        </View>
      )}
      ListEmptyComponent={<EmptyState icon="notifications-outline" title="Keine Mitteilungen" text="Hier erscheinen App-News und spätere Erinnerungen." />}
      renderItem={({ item }) => (
        <Pressable onPress={() => markRead(item)} style={[styles.notificationCard, !item.read_at && styles.notificationUnread]}>
          <View style={styles.cardTopline}>
            <View style={styles.smallIcon}>
              <Ionicons name="notifications-outline" size={18} color="#1D4ED8" />
            </View>
            {!item.read_at ? <Text style={styles.unreadBadge}>Neu</Text> : <Text style={styles.newsDate}>{formatDate(item.created_at)}</Text>}
          </View>
          <Text style={styles.cardTitle}>{item.title}</Text>
          {item.body ? <Text style={styles.bodyText}>{item.body}</Text> : null}
          {!item.read_at ? <Text style={styles.notificationHint}>Antippen, um als gelesen zu markieren.</Text> : null}
        </Pressable>
      )}
    />
  );
}

function MemberCardScreen() {
  const [card, setCard] = useState<MemberCard | null>(null);

  useEffect(() => {
    let active = true;
    apiFetch<{ card?: MemberCard }>("/api/mobile/member-card")
      .then((data) => {
        if (active) setCard(data.card ?? null);
      })
      .catch(() => {
        if (active) setCard(null);
      });
    return () => {
      active = false;
    };
  }, []);

  return (
    <ScrollView contentContainerStyle={styles.content}>
      <SectionHeader icon="qr-code-outline" title="Mitgliederausweis" text="Deine Clubano-Identität für künftige Vereinsfunktionen." />
      {!card ? (
        <View style={styles.surfaceCard}>
          <ActivityIndicator />
          <Text style={styles.muted}>Ausweis wird geladen...</Text>
        </View>
      ) : (
        <View style={styles.memberPass}>
          <View style={styles.passTop}>
            <View style={styles.passLogo}>
              {card.logo_data_uri ? (
                <Image source={{ uri: card.logo_data_uri }} style={styles.passLogoImage} resizeMode="contain" />
              ) : (
                <Ionicons name="people" size={28} color="#ffffff" />
              )}
            </View>
            <View style={styles.passClub}>
              <Text style={styles.passKicker}>Mein Clubano</Text>
              <Text style={styles.passClubName}>{card.club_name ?? "Verein"}</Text>
            </View>
          </View>

          <View>
            <Text style={styles.passLabel}>Mitglied</Text>
            <Text style={styles.passName}>{card.full_name}</Text>
            {card.member_number ? <Text style={styles.passNumber}>Nr. {card.member_number}</Text> : null}
          </View>

          <View style={styles.qrBox}>
            <Image source={{ uri: card.qr_code_data_uri }} style={styles.qrImage} resizeMode="contain" />
          </View>

          <Text style={styles.passHint}>Dieser QR-Code weist dich als Mitglied aus. Er enthält keine Adresse, keine E-Mail und kein Guthaben.</Text>
        </View>
      )}
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

  useEffect(() => {
    setForm(initial);
  }, [initial]);

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
      <SectionHeader icon="person-circle-outline" title="Meine Stammdaten" text="Prüfe deine gespeicherten Daten und reiche Änderungen zur Prüfung ein." />
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
          <TextInput style={styles.input} placeholderTextColor="#94A3B8" value={(form as any)[key]} onChangeText={(value) => setForm({ ...form, [key]: value })} />
        </View>
      ))}
      <PrimaryButton label="Änderungen zur Prüfung senden" icon="send" onPress={submit} />
    </ScrollView>
  );
}

function Events() {
  const [events, setEvents] = useState<ClubEvent[]>([]);
  useEffect(() => {
    let active = true;
    apiFetch<{ events?: ClubEvent[] }>("/api/mobile/events")
      .then((data) => {
        if (active) setEvents(arrayOrEmpty(data.events));
      })
      .catch(() => {
        if (active) setEvents([]);
      });
    return () => {
      active = false;
    };
  }, []);

  async function respond(event: ClubEvent, status: string) {
    try {
      await apiFetch(`/api/mobile/events/${event.id}/response`, { method: "POST", body: JSON.stringify({ status }) });
      const data = await apiFetch<{ events?: ClubEvent[] }>("/api/mobile/events");
      setEvents(arrayOrEmpty(data.events));
    } catch (error) {
      Alert.alert("Antwort nicht gespeichert", error instanceof Error ? error.message : "Bitte versuche es erneut.");
    }
  }

  return (
    <FlatList
      contentContainerStyle={styles.content}
      data={events}
      keyExtractor={(item) => String(item.id)}
      ListHeaderComponent={<SectionHeader icon="calendar-outline" title="Termine" text="Alles, was für deinen Verein ansteht." />}
      ListEmptyComponent={<EmptyState icon="calendar-clear-outline" title="Keine Termine" text="Aktuell sind keine kommenden Veranstaltungen für die App freigegeben." />}
      renderItem={({ item }) => (
      <View style={styles.surfaceCard}>
        <View style={styles.cardTopline}>
          <View style={styles.smallIcon}>
            <Ionicons name="calendar-clear-outline" size={18} color="#1D4ED8" />
          </View>
          <Text style={styles.statusBadge}>{item.invitation?.label ?? "Keine Rückmeldung"}</Text>
        </View>
        <Text style={styles.cardTitle}>{item.title}</Text>
        <InfoRow icon="time-outline" text={formatDate(item.starts_at)} />
        <InfoRow icon="location-outline" text={item.location ?? "Ort offen"} />
        {!!item.price_label && <InfoRow icon="pricetag-outline" text={item.price_label} />}
        <View style={styles.responseGrid}>
          <ActionChip label="Zusage" icon="checkmark" tone="positive" onPress={() => respond(item, "accepted")} />
          <ActionChip label="Vielleicht" icon="help" tone="neutral" onPress={() => respond(item, "maybe")} />
          <ActionChip label="Absage" icon="close" tone="danger" onPress={() => respond(item, "declined")} />
        </View>
      </View>
    )} />
  );
}

function Shifts() {
  const [events, setEvents] = useState<Array<{ id: number; title: string; shifts: Shift[] }>>([]);
  useEffect(() => {
    let active = true;
    apiFetch<{ events?: Array<{ id: number; title: string; shifts?: Shift[] }> }>("/api/mobile/shifts")
      .then((data) => {
        if (active) {
          setEvents(arrayOrEmpty(data.events).map((event) => ({
            ...event,
            shifts: arrayOrEmpty(event.shifts)
          })));
        }
      })
      .catch(() => {
        if (active) setEvents([]);
      });
    return () => {
      active = false;
    };
  }, []);

  return (
    <ScrollView contentContainerStyle={styles.content}>
      <SectionHeader icon="people-outline" title="Dienstplan" text="Freigegebene Dienste und Besetzungen auf einen Blick." />
      {events.length === 0 && <EmptyState icon="people-outline" title="Kein Dienstplan" text="Aktuell ist kein Dienstplan für die App freigegeben." />}
      {events.map((event) => (
        <View key={event.id} style={styles.surfaceCard}>
          <Text style={styles.cardTitle}>{event.title}</Text>
          {arrayOrEmpty(event.shifts).map((shift) => (
            <View key={shift.id} style={styles.shift}>
              <Text style={styles.label}>{shift.title}</Text>
              <InfoRow icon="time-outline" text={formatDate(shift.starts_at)} />
              <InfoRow icon="person-add-outline" text={`Offen: ${shift.open_slots}`} />
              <Text style={styles.bodyText}>{arrayOrEmpty(shift.assignments).map((a) => a.is_me ? `${a.name} (du)` : a.name).join(", ") || "Noch niemand eingetragen"}</Text>
            </View>
          ))}
        </View>
      ))}
    </ScrollView>
  );
}

function Documents() {
  const [documents, setDocuments] = useState<DocumentItem[]>([]);
  useEffect(() => {
    let active = true;
    apiFetch<{ documents?: DocumentItem[] }>("/api/mobile/documents")
      .then((data) => {
        if (active) setDocuments(arrayOrEmpty(data.documents));
      })
      .catch(() => {
        if (active) setDocuments([]);
      });
    return () => {
      active = false;
    };
  }, []);
  return (
    <FlatList
      contentContainerStyle={styles.content}
      data={documents}
      keyExtractor={(item) => String(item.id)}
      ListHeaderComponent={<SectionHeader icon="document-text-outline" title="Satzung & Beitragsordnung" text="Die wichtigsten Vereinsgrundlagen griffbereit." />}
      ListEmptyComponent={<EmptyState icon="document-outline" title="Keine Dokumente" text="Aktuell sind keine Dokumente für die App freigegeben." />}
      renderItem={({ item }) => (
      <View style={styles.surfaceCard}>
        <View style={styles.smallIcon}>
          <Ionicons name="reader-outline" size={18} color="#4338CA" />
        </View>
        <Text style={styles.cardTitle}>{item.title}</Text>
        <Text style={styles.muted}>{item.description ?? "Freigegebenes Vereinsdokument"}</Text>
      </View>
    )} />
  );
}

function News() {
  const [news, setNews] = useState<NewsItem[]>([]);
  useEffect(() => {
    let active = true;
    apiFetch<{ news?: NewsItem[] }>("/api/mobile/news")
      .then((data) => {
        if (active) setNews(arrayOrEmpty(data.news));
      })
      .catch(() => {
        if (active) setNews([]);
      });
    return () => {
      active = false;
    };
  }, []);

  return (
    <FlatList
      contentContainerStyle={styles.content}
      data={news}
      keyExtractor={(item) => String(item.id)}
      ListHeaderComponent={<SectionHeader icon="megaphone-outline" title="Vereinsnews" text="Offizielle Mitteilungen des Vereins, ruhig und ohne Chat." />}
      ListEmptyComponent={<EmptyState icon="megaphone-outline" title="Noch keine Mitteilungen" text="Aktuell gibt es keine veröffentlichten Mitteilungen." />}
      renderItem={({ item }) => (
        <View style={styles.newsCard}>
          <View style={styles.cardTopline}>
            <View style={styles.smallIcon}>
              <Ionicons name="megaphone-outline" size={18} color="#1D4ED8" />
            </View>
            <Text style={styles.newsDate}>{formatDate(item.published_at)}</Text>
          </View>
          <Text style={styles.cardTitle}>{item.title}</Text>
          {item.teaser ? <Text style={styles.muted}>{item.teaser}</Text> : null}
          {item.body ? <Text style={styles.bodyText}>{item.body}</Text> : null}
        </View>
      )}
    />
  );
}

function Contact() {
  const [contact, setContact] = useState<any>(null);
  useEffect(() => {
    let active = true;
    apiFetch<{ contact?: any }>("/api/mobile/contact")
      .then((data) => {
        if (active) setContact(data.contact ?? null);
      })
      .catch(() => {
        if (active) setContact(null);
      });
    return () => {
      active = false;
    };
  }, []);
  return (
    <ScrollView contentContainerStyle={styles.content}>
      <SectionHeader icon="mail-outline" title="Kontakt zum Verein" text="Wenn etwas unklar ist, findest du hier die offiziellen Kontaktdaten." />
      <View style={styles.surfaceCard}>
        <View style={styles.smallIcon}>
          <Ionicons name="business-outline" size={18} color="#047857" />
        </View>
        <Text style={styles.cardTitle}>{contact?.club_name ?? "Verein"}</Text>
        <InfoRow icon="mail-outline" text={contact?.email ?? "Keine E-Mail hinterlegt"} />
        {!!contact?.phone && <InfoRow icon="call-outline" text={contact.phone} />}
        <InfoRow icon="location-outline" text={[contact?.address, contact?.zip, contact?.city].filter(Boolean).join(", ") || "Keine Anschrift hinterlegt"} />
      </View>
    </ScrollView>
  );
}

function Legal() {
  return (
    <ScrollView contentContainerStyle={styles.content}>
      <SectionHeader icon="shield-checkmark-outline" title="Rechtliches & Support" text="Datenschutz und Hilfe zur Mein Clubano App." />
      <LinkCard
        icon="lock-closed-outline"
        title="Datenschutzerklärung"
        text="Hier findest du die Datenschutzhinweise zu Clubano."
        actionLabel="Datenschutz öffnen"
        onPress={() => openExternal("https://clubano.de/datenschutzerklaerung/")}
      />
      <LinkCard
        icon="help-circle-outline"
        title="Support"
        text="Bei technischen Fragen zur App erreichst du den Support per E-Mail."
        actionLabel="kontakt@motdesign.de"
        onPress={() => openExternal("mailto:kontakt@motdesign.de?subject=Support%20Mein%20Clubano")}
      />
    </ScrollView>
  );
}

function LinkCard({ icon, title, text, actionLabel, onPress }: { icon: IconName; title: string; text: string; actionLabel: string; onPress: () => void }) {
  return (
    <View style={styles.surfaceCard}>
      <View style={styles.cardTopline}>
        <View style={styles.smallIcon}>
          <Ionicons name={icon} size={18} color="#1D4ED8" />
        </View>
        <Ionicons name="open-outline" size={18} color="#52708F" />
      </View>
      <Text style={styles.cardTitle}>{title}</Text>
      <Text style={styles.bodyText}>{text}</Text>
      <Pressable onPress={onPress} style={styles.linkButton}>
        <Text style={styles.linkButtonText}>{actionLabel}</Text>
        <Ionicons name="arrow-forward" size={16} color="#1D4ED8" />
      </Pressable>
    </View>
  );
}

async function openExternal(url: string) {
  const supported = await Linking.canOpenURL(url);

  if (!supported) {
    Alert.alert("Nicht verfügbar", "Dieser Link kann auf dem Gerät nicht geöffnet werden.");
    return;
  }

  await Linking.openURL(url);
}

function SectionHeader({ icon, title, text }: { icon: IconName; title: string; text: string }) {
  return (
    <View style={styles.sectionHeader}>
      <View style={styles.sectionIcon}>
        <Ionicons name={icon} size={22} color="#1E3A8A" />
      </View>
      <View style={styles.sectionCopy}>
        <Text style={styles.heading}>{title}</Text>
        <Text style={styles.muted}>{text}</Text>
      </View>
    </View>
  );
}

function EmptyState({ icon, title, text }: { icon: IconName; title: string; text: string }) {
  return (
    <View style={styles.emptyState}>
      <Ionicons name={icon} size={26} color="#52708F" />
      <Text style={styles.emptyTitle}>{title}</Text>
      <Text style={styles.emptyText}>{text}</Text>
    </View>
  );
}

function InfoRow({ icon, text }: { icon: IconName; text: string }) {
  return (
    <View style={styles.infoRow}>
      <Ionicons name={icon} size={16} color="#52708F" />
      <Text style={styles.infoText}>{text}</Text>
    </View>
  );
}

function PrimaryButton({ label, icon, onPress, disabled = false }: { label: string; icon: IconName; onPress: () => void; disabled?: boolean }) {
  return (
    <Pressable onPress={onPress} disabled={disabled} style={[styles.primaryButton, disabled && styles.primaryButtonDisabled]}>
      <Text style={styles.primaryButtonText}>{label}</Text>
      <Ionicons name={icon} size={18} color="#ffffff" />
    </Pressable>
  );
}

function ActionChip({ label, icon, tone, onPress }: { label: string; icon: IconName; tone: "positive" | "neutral" | "danger"; onPress: () => void }) {
  const toneStyle = actionTones[tone];

  return (
    <Pressable onPress={onPress} style={[styles.actionChip, { backgroundColor: toneStyle.bg, borderColor: toneStyle.border }]}>
      <Ionicons name={icon} size={16} color={toneStyle.text} />
      <Text style={[styles.actionChipText, { color: toneStyle.text }]}>{label}</Text>
    </Pressable>
  );
}

function Centered({ children }: { children: React.ReactNode }) {
  return <SafeAreaView style={styles.centered}>{children}</SafeAreaView>;
}

function formatDate(value?: string | null) {
  if (!value) {
    return "Termin offen";
  }
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) {
    return "Termin offen";
  }
  const pad = (part: number) => String(part).padStart(2, "0");
  return `${pad(date.getDate())}.${pad(date.getMonth() + 1)}.${date.getFullYear()} ${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

const actionTones = {
  positive: { bg: "#EAF8EF", border: "#BFE8CC", text: "#047857" },
  neutral: { bg: "#FFF8E6", border: "#F7D88B", text: "#92400E" },
  danger: { bg: "#FFF1F2", border: "#FDA4AF", text: "#BE123C" }
};

const styles = StyleSheet.create({
  shell: { flex: 1, backgroundColor: "#F4F8FB" },
  centered: { flex: 1, alignItems: "center", justifyContent: "center", backgroundColor: "#F4F8FB" },
  login: { flex: 1, justifyContent: "center", padding: 18, gap: 14 },
  loginHero: { backgroundColor: "#123E69", borderRadius: 8, padding: 22, gap: 10 },
  loginMark: { width: 58, height: 58, borderRadius: 8, alignItems: "center", justifyContent: "center", backgroundColor: "#1D75BD" },
  loginTitle: { fontSize: 30, fontWeight: "900", color: "#FFFFFF" },
  loginText: { color: "#DDEBFA", fontSize: 15, lineHeight: 22 },
  loginPanel: { backgroundColor: "#FFFFFF", borderRadius: 8, padding: 18, gap: 12, borderWidth: 1, borderColor: "#D9E6F2" },
  panelTitle: { color: "#102A43", fontSize: 20, fontWeight: "800" },
  header: { paddingHorizontal: 16, paddingTop: 14, paddingBottom: 12, flexDirection: "row", justifyContent: "space-between", alignItems: "center" },
  brandRow: { flexDirection: "row", alignItems: "center", gap: 10, flex: 1 },
  appMark: { width: 40, height: 40, borderRadius: 8, alignItems: "center", justifyContent: "center", backgroundColor: "#1D75BD" },
  iconButton: { width: 40, height: 40, borderRadius: 8, alignItems: "center", justifyContent: "center", backgroundColor: "#EAF4FF", borderWidth: 1, borderColor: "#CFE3F7" },
  kicker: { color: "#52708F", fontSize: 11, fontWeight: "800", textTransform: "uppercase" },
  title: { fontSize: 24, fontWeight: "900", color: "#102A43" },
  heading: { fontSize: 22, fontWeight: "900", color: "#102A43" },
  muted: { color: "#52708F", lineHeight: 20 },
  tabs: { flexDirection: "row", flexWrap: "wrap", gap: 8, paddingHorizontal: 16, paddingBottom: 12 },
  tab: { minHeight: 36, flexDirection: "row", alignItems: "center", gap: 6, borderWidth: 1, borderColor: "#D1E0EE", borderRadius: 8, paddingVertical: 8, paddingHorizontal: 10, backgroundColor: "#FFFFFF" },
  tabActive: { backgroundColor: "#123E69", borderColor: "#123E69" },
  tabText: { color: "#52708F", fontWeight: "800", fontSize: 13 },
  tabTextActive: { color: "#FFFFFF" },
  content: { padding: 16, gap: 14 },
  welcomeCard: { backgroundColor: "#123E69", borderRadius: 8, padding: 18, gap: 8 },
  welcomeKicker: { color: "#A7F3D0", fontSize: 12, fontWeight: "900", textTransform: "uppercase" },
  welcomeTitle: { color: "#FFFFFF", fontSize: 26, fontWeight: "900" },
  welcomeText: { color: "#DDEBFA", lineHeight: 21 },
  tileGrid: { flexDirection: "row", flexWrap: "wrap", gap: 12 },
  tile: { width: "48%", minHeight: 154, borderRadius: 8, padding: 14, gap: 8, borderWidth: 1, borderColor: "rgba(16, 42, 67, 0.08)" },
  tileIcon: { width: 42, height: 42, borderRadius: 8, alignItems: "center", justifyContent: "center" },
  tileTitle: { color: "#102A43", fontWeight: "900", fontSize: 17 },
  tileText: { color: "#46627F", lineHeight: 19, flex: 1 },
  surfaceCard: { backgroundColor: "#FFFFFF", borderRadius: 8, padding: 16, borderWidth: 1, borderColor: "#D9E6F2", gap: 9 },
  notificationHeader: { gap: 12 },
  notificationCard: { backgroundColor: "#FFFFFF", borderRadius: 8, padding: 16, borderWidth: 1, borderColor: "#D9E6F2", gap: 9 },
  notificationUnread: { borderColor: "#BBD7FF", backgroundColor: "#F8FBFF" },
  unreadBadge: { alignSelf: "flex-start", backgroundColor: "#EAF4FF", color: "#1D4ED8", borderRadius: 8, paddingVertical: 5, paddingHorizontal: 9, fontWeight: "900", fontSize: 12 },
  notificationHint: { color: "#52708F", fontSize: 12, fontWeight: "800" },
  memberPass: { backgroundColor: "#123E69", borderRadius: 8, padding: 18, gap: 18, borderWidth: 1, borderColor: "#0D2C4A" },
  passTop: { flexDirection: "row", alignItems: "center", gap: 12 },
  passLogo: { width: 60, height: 60, borderRadius: 8, alignItems: "center", justifyContent: "center", backgroundColor: "#1D75BD", overflow: "hidden" },
  passLogoImage: { width: 54, height: 54 },
  passClub: { flex: 1 },
  passKicker: { color: "#A7F3D0", fontSize: 11, fontWeight: "900", textTransform: "uppercase" },
  passClubName: { color: "#FFFFFF", fontWeight: "900", fontSize: 20 },
  passLabel: { color: "#A7C7E7", fontSize: 12, fontWeight: "900", textTransform: "uppercase" },
  passName: { color: "#FFFFFF", fontSize: 28, fontWeight: "900" },
  passNumber: { marginTop: 4, color: "#DDEBFA", fontWeight: "800" },
  qrBox: { alignItems: "center", justifyContent: "center", backgroundColor: "#FFFFFF", borderRadius: 8, padding: 14 },
  qrImage: { width: 250, height: 250 },
  passHint: { color: "#DDEBFA", lineHeight: 20, fontSize: 13 },
  newsCard: { backgroundColor: "#FFFFFF", borderRadius: 8, padding: 16, borderWidth: 1, borderColor: "#CFE3F7", gap: 10, shadowColor: "#123E69", shadowOpacity: 0.08, shadowRadius: 10, shadowOffset: { width: 0, height: 4 } },
  cardTopline: { flexDirection: "row", justifyContent: "space-between", alignItems: "center", gap: 8 },
  newsDate: { color: "#52708F", fontWeight: "800", fontSize: 12 },
  smallIcon: { width: 34, height: 34, borderRadius: 8, alignItems: "center", justifyContent: "center", backgroundColor: "#EAF4FF" },
  cardTitle: { fontSize: 18, fontWeight: "900", color: "#102A43" },
  sectionHeader: { flexDirection: "row", alignItems: "center", gap: 12, backgroundColor: "#FFFFFF", borderRadius: 8, padding: 14, borderWidth: 1, borderColor: "#D9E6F2" },
  sectionIcon: { width: 42, height: 42, borderRadius: 8, alignItems: "center", justifyContent: "center", backgroundColor: "#EAF4FF" },
  sectionCopy: { flex: 1, gap: 3 },
  field: { gap: 6, backgroundColor: "#FFFFFF", borderRadius: 8, padding: 12, borderWidth: 1, borderColor: "#D9E6F2" },
  label: { fontWeight: "800", color: "#274563" },
  input: { backgroundColor: "#FFFFFF", borderWidth: 1, borderColor: "#C9D8E6", borderRadius: 8, padding: 12, color: "#102A43" },
  primaryButton: { minHeight: 48, borderRadius: 8, paddingHorizontal: 16, alignItems: "center", justifyContent: "center", flexDirection: "row", gap: 8, backgroundColor: "#1D75BD" },
  primaryButtonDisabled: { opacity: 0.55 },
  primaryButtonText: { color: "#FFFFFF", fontWeight: "900" },
  statusBadge: { alignSelf: "flex-start", backgroundColor: "#EEF8F3", color: "#047857", borderRadius: 8, paddingVertical: 5, paddingHorizontal: 9, fontWeight: "800", fontSize: 12 },
  responseGrid: { flexDirection: "row", flexWrap: "wrap", gap: 8, paddingTop: 4 },
  actionChip: { flexDirection: "row", alignItems: "center", gap: 5, borderRadius: 8, borderWidth: 1, paddingVertical: 8, paddingHorizontal: 10 },
  actionChipText: { fontWeight: "900", fontSize: 13 },
  infoRow: { flexDirection: "row", alignItems: "center", gap: 7 },
  infoText: { color: "#46627F", flex: 1, lineHeight: 20 },
  bodyText: { color: "#102A43", lineHeight: 20 },
  linkButton: { minHeight: 44, borderRadius: 8, paddingHorizontal: 12, flexDirection: "row", alignItems: "center", justifyContent: "space-between", backgroundColor: "#EAF4FF", borderWidth: 1, borderColor: "#CFE3F7" },
  linkButtonText: { color: "#1D4ED8", fontWeight: "900" },
  emptyState: { backgroundColor: "#FFFFFF", borderRadius: 8, padding: 18, borderWidth: 1, borderColor: "#D9E6F2", alignItems: "flex-start", gap: 8 },
  emptyTitle: { color: "#102A43", fontWeight: "900", fontSize: 18 },
  emptyText: { color: "#52708F", lineHeight: 20 },
  shift: { borderTopWidth: 1, borderTopColor: "#E4EDF5", paddingTop: 12, gap: 5 }
});
