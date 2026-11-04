import { useState } from 'react';
import { ActivityIndicator, Pressable, StyleSheet, Text, View } from 'react-native';

import { fetchPlace, usePlaceSuggestions } from '@/api/hooks';
import type { Place } from '@/api/types';
import { Field } from '@/components/ui';
import { colors, fonts, minTouchSize } from '@/constants/theme';
import { useDebouncedValue } from '@/hooks/use-debounced-value';
import { randomId } from '@/lib/id';

type Props = {
  locationId: string;
  value: string;
  // Set once the customer picked a suggestion; typing again clears it.
  located: boolean;
  onType: (text: string) => void;
  onPick: (place: Place) => void;
  error?: string;
};

/**
 * The delivery street with Google suggestions under it, so the customer picks the address
 * Google knows instead of guessing how to write it. Typing without picking still works:
 * the server then looks the text up on its own.
 */
export function AddressSearch({ locationId, value, located, onType, onPick, error }: Props) {
  // One Google session per search, from the first letter to the pick, billed as one lookup.
  const [session, setSession] = useState(randomId);
  const [hidden, setHidden] = useState(false);
  const [picking, setPicking] = useState(false);
  const [needsNumber, setNeedsNumber] = useState(false);

  const query = useDebouncedValue(value.trim(), 300);
  const searching = !located && !hidden && query.length >= 3;
  const suggestions = usePlaceSuggestions(query, locationId, session, searching);
  const items = searching ? (suggestions.data ?? []) : [];

  async function pick(placeId: string) {
    setPicking(true);
    try {
      const place = await fetchPlace(placeId, session);
      onPick(place);
      setNeedsNumber(!place.precise);
      setHidden(true);
    } catch {
      // Keep what was typed; the server will look it up when the cost is calculated.
      setHidden(true);
    } finally {
      setPicking(false);
      setSession(randomId());
    }
  }

  return (
    <View style={{ gap: 6 }}>
      <Field
        label="Adresa de livrare"
        placeholder="Începe să scrii strada"
        value={value}
        onChangeText={(text) => {
          setHidden(false);
          setNeedsNumber(false);
          onType(text);
        }}
        autoComplete="street-address"
        autoCorrect={false}
        error={error}
      />
      {needsNumber ? <Text style={styles.hint}>Adaugă și numărul străzii, ca livratorul să găsească ușor adresa.</Text> : null}
      {picking ? <ActivityIndicator color={colors.accent} style={{ alignSelf: 'flex-start' }} /> : null}
      {items.length > 0 && !picking ? (
        <View style={styles.list} accessibilityRole="list">
          {items.map((item, index) => (
            <Pressable
              key={item.place_id}
              accessibilityRole="button"
              accessibilityLabel={[item.main, item.secondary].filter(Boolean).join(', ')}
              onPress={() => pick(item.place_id)}
              style={({ pressed }) => [styles.item, index > 0 && styles.divider, pressed && { backgroundColor: colors.border }]}>
              <Text style={styles.main} numberOfLines={1}>
                {item.main}
              </Text>
              {item.secondary ? (
                <Text style={styles.secondary} numberOfLines={1}>
                  {item.secondary}
                </Text>
              ) : null}
            </Pressable>
          ))}
          <Text style={styles.attribution}>Sugestii de la Google</Text>
        </View>
      ) : null}
    </View>
  );
}

const styles = StyleSheet.create({
  hint: { fontFamily: fonts.medium, fontSize: 13, color: colors.accent },
  list: { borderWidth: 1, borderColor: colors.border, borderRadius: 12, backgroundColor: colors.surface, overflow: 'hidden' },
  item: { minHeight: minTouchSize, justifyContent: 'center', paddingHorizontal: 14, paddingVertical: 8 },
  divider: { borderTopWidth: 1, borderTopColor: colors.divider },
  main: { fontFamily: fonts.semibold, fontSize: 15, color: colors.text },
  secondary: { fontFamily: fonts.regular, fontSize: 13, color: colors.textMuted },
  attribution: { fontFamily: fonts.regular, fontSize: 11, color: colors.textTertiary, paddingHorizontal: 14, paddingVertical: 6, textAlign: 'right' },
});
