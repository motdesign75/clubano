import React, { useEffect, useRef, useState } from "react";
import { ActivityIndicator, Alert, Linking, Pressable, ScrollView, StyleSheet, Switch, Text, TextInput, View } from "react-native";
import { Ionicons } from "@expo/vector-icons";
import { apiFetch } from "./api";
import type { ClubEvent } from "./types";

type Field = { slug: string; label: string; type: string; required: boolean; help: string; options: string[] };
type Schema = { title: string; description: string; native_supported: boolean; fields: Field[]; defaults: Record<string, string>; max_participants: number; member_price_cents: number; guest_price_cents: number; currency: string };
type Answer = string | boolean | string[];
type Guest = { first_name: string; last_name: string; email: string };

export function EventBookingForm({ event, onClose, onBooked }: { event: ClubEvent; onClose: () => void; onBooked: () => void }) {
  const [schema, setSchema] = useState<Schema | null>(null);
  const [fields, setFields] = useState<Record<string, Answer>>({});
  const [guests, setGuests] = useState<Guest[]>([]);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const [attempt, setAttempt] = useState(0);
  const submitting = useRef(false);
  useEffect(() => {
    let active = true;
    setError(null);
    apiFetch<Schema>(`/api/mobile/events/${event.id}/booking`).then((data) => {
      if (active) { setSchema(data); setFields(data.defaults); }
    }).catch((failure) => { if (active) setError(failure.message); });
    return () => { active = false; };
  }, [event.id, attempt]);

  const update = (slug: string, value: Answer) => setFields((current) => ({ ...current, [slug]: value }));
  const total = schema ? schema.member_price_cents + guests.length * schema.guest_price_cents : 0;
  const amount = schema ? `${(total / 100).toFixed(2).replace(".", ",")} ${schema.currency}` : "";
  const openWeb = () => event.booking_url && Linking.openURL(event.booking_url).catch(() => setError("Das Vereinsformular konnte nicht geöffnet werden."));

  async function submit() {
    if (!schema || submitting.current) return;
    submitting.current = true; setBusy(true); setError(null);
    try {
      const result = await apiFetch<{ reference: string }>(`/api/mobile/events/${event.id}/booking`, {
        method: "POST",
        body: JSON.stringify({ fields, participants: guests, participant_count: 1 + guests.length, expected_total_cents: total })
      });
      Alert.alert("Anmeldung eingegangen", `Buchungsnummer: ${result.reference}`);
      onBooked();
    } catch (failure) {
      setError(failure instanceof Error ? failure.message : "Die Anmeldung konnte nicht gespeichert werden.");
    } finally { submitting.current = false; setBusy(false); }
  }

  return <ScrollView contentContainerStyle={styles.page} keyboardShouldPersistTaps="handled">
    <Pressable accessibilityRole="button" accessibilityLabel="Zurück zu Terminen" disabled={busy} onPress={onClose} style={styles.row}>
      <Ionicons name="arrow-back" size={24} color="#173F65" /><Text style={styles.label}>Termine</Text>
    </Pressable>
    <Text style={styles.title}>{event.title}</Text>
    {!!error && <Text accessibilityRole="alert" style={styles.error}>{error}</Text>}
    {!schema && !error && <ActivityIndicator />}
    {!schema && !!error && <Pressable accessibilityRole="button" onPress={() => setAttempt(attempt + 1)}><Text style={styles.label}>Erneut versuchen</Text></Pressable>}
    {schema && !schema.native_supported && <Pressable accessibilityRole="button" style={styles.submit} onPress={openWeb}><Text style={styles.submitText}>Vereinsformular öffnen</Text></Pressable>}
    {schema?.native_supported && <>
      {!!schema.description && <Text style={styles.text}>{schema.description}</Text>}
      <Text style={styles.text}>Deine Teilnahme: {(schema.member_price_cents / 100).toFixed(2).replace(".", ",")} {schema.currency}{"\n"}Je weitere Person: {(schema.guest_price_cents / 100).toFixed(2).replace(".", ",")} {schema.currency}</Text>
      {schema.fields.map((field) => {
        const value = fields[field.slug];
        const label = `${field.label}${field.required ? " *" : ""}`;
        const locked = ["first_name", "last_name"].includes(field.slug);
        if (field.type === "divider") return <View key={field.slug} style={styles.divider} />;
        if (["heading", "content"].includes(field.type)) return <View key={field.slug}><Text style={field.type === "heading" ? styles.label : styles.text}>{field.label}</Text>{!!field.help && <Text style={styles.text}>{field.help}</Text>}</View>;
        return <View key={field.slug} style={styles.field}>
          <Text style={styles.label}>{label}</Text>
          {!!field.help && <Text style={styles.text}>{field.help}</Text>}
          {field.type === "checkbox" ? <Switch accessibilityLabel={label} disabled={busy} value={value === true} onValueChange={(checked) => update(field.slug, checked)} />
            : ["select", "radio", "checkbox_group"].includes(field.type) ? field.options.map((option) => {
              const multi = field.type === "checkbox_group";
              const values = Array.isArray(value) ? value : [];
              const selected = multi ? values.includes(option) : value === option;
              return <Pressable key={option} accessibilityRole={multi ? "checkbox" : "radio"} accessibilityState={{ checked: selected }} disabled={busy} style={styles.row}
                onPress={() => update(field.slug, multi ? (selected ? values.filter((entry) => entry !== option) : [...values, option]) : (selected && !field.required ? "" : option))}>
                <Ionicons name={multi ? (selected ? "checkbox" : "square-outline") : (selected ? "radio-button-on" : "radio-button-off")} size={24} color="#173F65" />
                <Text style={[styles.text, { flex: 1 }]}>{option}</Text>
              </Pressable>;
            }) : <TextInput accessibilityLabel={label} style={styles.input} editable={!busy && !locked} value={typeof value === "string" ? value : ""}
              placeholder={field.type === "date" ? "JJJJ-MM-TT" : undefined} multiline={field.type === "textarea"}
              autoCapitalize={field.type === "email" ? "none" : "sentences"}
              keyboardType={field.type === "email" ? "email-address" : field.type === "number" ? "decimal-pad" : field.type === "tel" ? "phone-pad" : "default"}
              onChangeText={(text) => update(field.slug, text)} />}
        </View>;
      })}
      <View style={styles.divider} />
      <Text style={styles.label}>Weitere Teilnehmende ({guests.length})</Text>
      {guests.map((guest, index) => <View key={index} style={styles.field}>
        <View style={styles.row}><Text style={[styles.label, { flex: 1 }]}>Person {index + 2}</Text>
          <Pressable disabled={busy} accessibilityRole="button" accessibilityLabel={`Person ${index + 2} entfernen`} onPress={() => setGuests(guests.filter((_, position) => position !== index))} style={styles.iconButton}>
            <Ionicons name="trash-outline" size={22} color="#B42338" />
          </Pressable>
        </View>
        {([['first_name', 'Vorname *'], ['last_name', 'Nachname *'], ['email', 'E-Mail (optional)']] as const).map(([key, label]) =>
          <TextInput key={key} accessibilityLabel={label} placeholder={label} style={styles.input} editable={!busy} value={guest[key]}
            autoCapitalize={key === 'email' ? 'none' : 'words'} keyboardType={key === 'email' ? 'email-address' : 'default'}
            onChangeText={(text) => setGuests(guests.map((entry, position) => position === index ? { ...entry, [key]: text } : entry))} />)}
      </View>)}
      {guests.length + 1 < schema.max_participants && <Pressable accessibilityRole="button" disabled={busy} style={styles.row} onPress={() => setGuests([...guests, { first_name: "", last_name: "", email: "" }])}>
        <Ionicons name="person-add-outline" size={24} color="#173F65" /><Text style={styles.label}>Person hinzufügen</Text>
      </Pressable>}
      <Text style={styles.label}>{1 + guests.length} Teilnehmende · Gesamt: {amount}</Text>
      <Pressable accessibilityRole="button" disabled={busy} style={[styles.submit, busy && { opacity: 0.5 }]} onPress={() => Alert.alert("Anmeldung bestätigen", `${1 + guests.length} Teilnehmende für insgesamt ${amount} verbindlich anmelden?`, [
        { text: "Zurück", style: "cancel" }, { text: total > 0 ? "Zahlungspflichtig anmelden" : "Verbindlich anmelden", onPress: submit }
      ])}><Text style={styles.submitText}>{busy ? "Wird gespeichert…" : total > 0 ? "Zahlungspflichtig anmelden" : "Verbindlich anmelden"}</Text></Pressable>
      <Pressable accessibilityRole="link" disabled={busy} onPress={openWeb}><Text style={styles.text}>Gutschein oder Organisationsanmeldung</Text></Pressable>
    </>}
  </ScrollView>;
}

const styles = StyleSheet.create({
  page: { padding: 20, gap: 18, paddingBottom: 40 },
  title: { fontSize: 24, fontWeight: "700", color: "#102A43" },
  label: { fontSize: 16, fontWeight: "600", color: "#173F65", flexShrink: 1 },
  text: { fontSize: 15, color: "#52677D", flexShrink: 1 },
  error: { fontSize: 15, color: "#B42338" },
  field: { gap: 10 }, row: { flexDirection: "row", alignItems: "center", gap: 12, minHeight: 44 },
  input: { borderWidth: 1, borderColor: "#CBD5E1", borderRadius: 6, padding: 12, minHeight: 48, fontSize: 16, color: "#102A43", backgroundColor: "#FFFFFF" },
  divider: { height: 1, backgroundColor: "#CBD5E1" },
  submit: { padding: 16, minHeight: 48, borderRadius: 6, backgroundColor: "#173F65", alignItems: "center" },
  submitText: { fontSize: 16, fontWeight: "700", color: "#FFFFFF", textAlign: "center" },
  iconButton: { width: 44, height: 44, alignItems: "center", justifyContent: "center" }
});
