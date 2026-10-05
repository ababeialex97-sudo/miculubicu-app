import { router, useLocalSearchParams } from 'expo-router';
import { ActivityIndicator, Linking, Pressable, ScrollView, StyleSheet, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

import { useConfig, useOrder } from '@/api/hooks';
import { Icon } from '@/components/icon';
import { AppText, Button, Centered, LoadError } from '@/components/ui';
import { colors, fonts, minTouchSize } from '@/constants/theme';
import { formatPrice } from '@/lib/money';
import { formatTime, headline, timeline } from '@/lib/status';

export default function OrderStatusScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const order = useOrder(Number(id));
  const config = useConfig();

  if (order.isPending) {
    return (
      <Centered>
        <ActivityIndicator color={colors.accent} size="large" />
      </Centered>
    );
  }
  if (order.isError) {
    return <LoadError message={order.error.message} onRetry={() => void order.refetch()} />;
  }

  const data = order.data;
  const location = config.data?.locations.find((l) => l.id === data.location_id);
  const steps = timeline(data.fulfillment);
  const currentIndex = steps.findIndex((s) => s.status === data.status);
  const cancelled = data.status === 'cancelled';
  const reachedAt = new Map(data.status_history.map((h) => [h.status, h.at]));

  return (
    <SafeAreaView edges={['top', 'bottom']} style={styles.screen}>
      <ScrollView contentContainerStyle={styles.content}>
        <View style={styles.topRow}>
          <Pressable accessibilityRole="button" accessibilityLabel="Înapoi" onPress={() => (router.canGoBack() ? router.back() : router.replace('/comenzi'))} style={styles.back}>
            <Icon name="back" color={colors.text} />
          </Pressable>
          <AppText variant="muted" style={{ fontFamily: fonts.semibold }}>
            Comanda #{data.number}
            {location ? ` · ${location.name}` : ''}
          </AppText>
        </View>

        <AppText variant="title" style={{ fontSize: 30, lineHeight: 34 }}>
          {headline(data.status, data.fulfillment)}
        </AppText>

        {cancelled ? (
          <View style={[styles.banner, { backgroundColor: colors.surface }]}>
            <AppText>Dacă ai întrebări, sună la restaurant.</AppText>
          </View>
        ) : (
          <View style={styles.timeline}>
            {steps.map((step, index) => {
              const done = index < currentIndex || data.status === 'completed';
              const current = index === currentIndex && data.status !== 'completed';
              const last = index === steps.length - 1;
              return (
                <View key={step.status} style={styles.step} accessibilityLabel={`${step.label}${done ? ', gata' : current ? ', acum' : ''}`}>
                  <View style={styles.rail}>
                    <View style={[styles.dot, done && styles.dotDone, current && styles.dotCurrent]}>
                      {done ? <Icon name="check" size={16} color={colors.onAccent} strokeWidth={3} /> : null}
                    </View>
                    {!last ? <View style={[styles.line, done && { backgroundColor: colors.accent }]} /> : null}
                  </View>
                  <View style={styles.stepText}>
                    <AppText
                      style={[
                        styles.stepLabel,
                        (done || current) && { fontFamily: fonts.bold, color: colors.text },
                        current && { color: colors.accent },
                      ]}>
                      {step.label}
                    </AppText>
                    {(done || current) && reachedAt.has(step.status) ? <AppText variant="muted">{formatTime(reachedAt.get(step.status) ?? '')}</AppText> : null}
                  </View>
                </View>
              );
            })}
          </View>
        )}

        <View style={styles.card}>
          {data.items.map((item, index) => (
            <View key={`${item.product_id}-${index}`} style={styles.itemRow}>
              <View style={{ flex: 1 }}>
                <AppText style={styles.itemText}>
                  {item.quantity} × {item.name}
                </AppText>
                {item.preferences ? <AppText variant="muted">{item.preferences}</AppText> : null}
              </View>
              <AppText style={styles.itemText}>{formatPrice(item.total)}</AppText>
            </View>
          ))}
          {Number.parseFloat(data.shipping_total) > 0 ? (
            <View style={styles.itemRow}>
              <AppText style={styles.itemText}>Livrare</AppText>
              <AppText style={styles.itemText}>{formatPrice(data.shipping_total)}</AppText>
            </View>
          ) : null}
          {Number.parseFloat(data.discount_total) > 0 ? (
            <View style={styles.itemRow}>
              <AppText style={[styles.itemText, styles.discount]}>Reducere{data.coupon_codes.length ? ` · ${data.coupon_codes.join(', ').toUpperCase()}` : ''}</AppText>
              <AppText style={[styles.itemText, styles.discount]}>−{formatPrice(data.discount_total)}</AppText>
            </View>
          ) : null}
          <View style={[styles.itemRow, styles.totalRow]}>
            <AppText style={[styles.itemText, { fontFamily: fonts.bold }]}>Total · {data.fulfillment === 'pickup' ? 'numerar la ridicare' : 'numerar la livrare'}</AppText>
            <AppText style={[styles.itemText, { fontFamily: fonts.bold }]}>{formatPrice(data.total)}</AppText>
          </View>
        </View>

        {location?.phone ? (
          <Button
            label={`Sună la ${location.name} · ${location.phone}`}
            variant="outline"
            right={<Icon name="phone" size={20} color={colors.accent} />}
            onPress={() => void Linking.openURL(`tel:${location.phone.replace(/\s/g, '')}`)}
          />
        ) : null}
      </ScrollView>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: colors.background },
  content: { padding: 20, gap: 18 },
  topRow: { flexDirection: 'row', alignItems: 'center', gap: 12 },
  back: { width: minTouchSize, height: minTouchSize, borderRadius: 22, backgroundColor: colors.surface, alignItems: 'center', justifyContent: 'center' },
  banner: { padding: 18, borderRadius: 18 },
  timeline: { paddingHorizontal: 4 },
  step: { flexDirection: 'row', gap: 14 },
  rail: { alignItems: 'center' },
  dot: { width: 28, height: 28, borderRadius: 14, borderWidth: 2, borderColor: colors.border, alignItems: 'center', justifyContent: 'center' },
  dotDone: { backgroundColor: colors.accent, borderColor: colors.accent },
  dotCurrent: { borderWidth: 3, borderColor: colors.accent, backgroundColor: colors.accentSoft },
  line: { width: 2, flex: 1, minHeight: 30, backgroundColor: colors.border },
  stepText: { paddingTop: 3, gap: 2, paddingBottom: 8 },
  stepLabel: { fontFamily: fonts.medium, fontSize: 16, color: colors.textMuted },
  card: { padding: 14, borderRadius: 14, backgroundColor: colors.surface, gap: 6 },
  itemRow: { flexDirection: 'row', justifyContent: 'space-between', gap: 12 },
  itemText: { fontFamily: fonts.regular, fontSize: 14, color: colors.text },
  discount: { color: colors.accent, fontFamily: fonts.semibold },
  totalRow: { paddingTop: 6, marginTop: 2, borderTopWidth: 1, borderTopColor: colors.border },
});
