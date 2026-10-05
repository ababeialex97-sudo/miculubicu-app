import { router, useFocusEffect } from 'expo-router';
import { useCallback } from 'react';
import { ActivityIndicator, Pressable, RefreshControl, ScrollView, StyleSheet, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

import { useConfig, useLoyalty } from '@/api/hooks';
import type { Coupon } from '@/api/types';
import { Icon } from '@/components/icon';
import { AppText, Button, LoadError } from '@/components/ui';
import { colors, fonts, minTouchSize } from '@/constants/theme';
import { resolveLocation } from '@/lib/location';
import { couponSubtitle, couponTitle, stampSlots } from '@/lib/loyalty';
import { formatPrice, toBani } from '@/lib/money';
import { useCart } from '@/store/cart';
import { useSession } from '@/store/session';

export default function LoyaltyScreen() {
  const token = useSession((s) => s.token);
  const loyalty = useLoyalty();
  const config = useConfig();
  const locationId = useCart((s) => s.locationId);
  const location = resolveLocation(config.data, locationId);
  const freeFrom = toBani(location?.free_delivery_threshold ?? 0);

  // Stamps change when staff complete an order, so the screen refreshes whenever it is opened.
  const { refetch } = loyalty;
  useFocusEffect(
    useCallback(() => {
      if (token) {
        void refetch();
      }
    }, [token, refetch]),
  );

  const freeDelivery =
    freeFrom > 0 ? (
      <View style={styles.coupon}>
        <View style={styles.badge}>
          <Icon name="truck" size={26} color={colors.accent} />
        </View>
        <View style={styles.couponText}>
          <AppText style={styles.couponTitle}>Livrare gratuită</AppText>
          <AppText variant="muted">La comenzi de peste {formatPrice(location?.free_delivery_threshold ?? 0)}</AppText>
        </View>
        <AppText style={styles.automatic}>Automat</AppText>
      </View>
    ) : null;

  if (!token) {
    return (
      <SafeAreaView edges={['top']} style={styles.screen}>
        <ScrollView contentContainerStyle={styles.content}>
          <AppText variant="title" style={styles.title}>
            Fidelitate
          </AppText>
          <LoyaltyCard stamps={0} required={4} rewardLabel={null} />
          <AppText>Intră în cont ca să strângi ștampile și să vezi cupoanele tale.</AppText>
          <Button label="Intră în cont" onPress={() => router.push('/autentificare')} />
          {freeDelivery}
        </ScrollView>
      </SafeAreaView>
    );
  }

  return (
    <SafeAreaView edges={['top']} style={styles.screen}>
      <ScrollView
        contentContainerStyle={styles.content}
        refreshControl={<RefreshControl refreshing={loyalty.isRefetching} onRefresh={() => void loyalty.refetch()} tintColor={colors.accent} />}
      >
        <AppText variant="title" style={styles.title}>
          Fidelitate
        </AppText>

        {loyalty.isPending ? (
          <ActivityIndicator color={colors.accent} style={{ marginTop: 40 }} />
        ) : loyalty.isError ? (
          <LoadError message={loyalty.error.message} onRetry={() => void loyalty.refetch()} />
        ) : (
          <>
            {loyalty.data.enabled ? <LoyaltyCard stamps={loyalty.data.stamps} required={loyalty.data.required} rewardLabel={loyalty.data.reward_label} /> : null}

            <View style={{ gap: 10 }}>
              <AppText variant="title" style={styles.sectionTitle}>
                Cupoanele tale
              </AppText>
              {loyalty.data.coupons.map((coupon) => (
                <CouponRow key={coupon.code} coupon={coupon} />
              ))}
              {freeDelivery}
              {loyalty.data.coupons.length === 0 && !freeDelivery ? <AppText variant="muted">Nu ai cupoane acum. Promoțiile noi apar aici.</AppText> : null}
            </View>
          </>
        )}
      </ScrollView>
    </SafeAreaView>
  );
}

function LoyaltyCard({ stamps, required, rewardLabel }: { stamps: number; required: number; rewardLabel: string | null }) {
  const slots = stampSlots(stamps, required);
  // Up to 5 boxes per row (4 stamps + the reward in the default setup).
  const columns = Math.min(slots.length + 1, 5);
  const width = `${100 / columns}%` as const;

  return (
    <View style={styles.card} accessible accessibilityLabel={`Cardul lui Bicu: ${stamps} din ${required} comenzi`}>
      <View style={styles.cardHeader}>
        <AppText style={styles.cardTitle}>Cardul lui Bicu</AppText>
        <AppText style={styles.cardCount}>
          {Math.min(stamps, required)} / {required} comenzi
        </AppText>
      </View>
      <View style={styles.stamps}>
        {slots.map((filled, i) => (
          <View key={i} style={[styles.slot, { width }]}>
            <View style={[styles.stamp, filled ? styles.stampFilled : styles.stampEmpty]}>{filled ? <Icon name="check" size={24} color={colors.accent} /> : null}</View>
          </View>
        ))}
        <View style={[styles.slot, { width }]}>
          <View style={[styles.stamp, styles.reward]}>
            <Icon name="gift" size={24} color={colors.onPrimary} />
          </View>
        </View>
      </View>
      <AppText style={styles.cardText}>
        După {required} {required === 1 ? 'comandă finalizată' : 'comenzi finalizate'} primești o reducere{rewardLabel ? ` de ${rewardLabel}` : ''} la următoarea comandă.
      </AppText>
    </View>
  );
}

function CouponRow({ coupon }: { coupon: Coupon }) {
  const inCart = useCart((s) => s.couponCodes.includes(coupon.code));
  const hasLines = useCart((s) => s.lines.length > 0);
  const addCoupon = useCart((s) => s.addCoupon);

  const use = () => {
    addCoupon(coupon.code);
    router.push(hasLines ? '/cos' : '/');
  };

  return (
    <View style={styles.coupon}>
      <View style={styles.badge}>
        <AppText style={styles.badgeText}>−{coupon.amount_label}</AppText>
      </View>
      <View style={styles.couponText}>
        <AppText style={styles.couponTitle}>{couponTitle(coupon)}</AppText>
        <AppText variant="muted">{couponSubtitle(coupon)}</AppText>
      </View>
      {inCart ? (
        <AppText style={styles.automatic}>În coș</AppText>
      ) : (
        <Pressable accessibilityRole="button" accessibilityLabel={`Folosește codul ${coupon.code}`} onPress={use} style={styles.use}>
          <AppText style={styles.useLabel}>Folosește</AppText>
        </Pressable>
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: colors.background },
  content: { padding: 20, paddingBottom: 40, gap: 18 },
  title: { fontSize: 30 },
  sectionTitle: { fontSize: 21 },
  card: { padding: 20, borderRadius: 20, backgroundColor: colors.accent, gap: 16 },
  cardHeader: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'baseline' },
  cardTitle: { fontFamily: fonts.heading, fontSize: 22, color: colors.onAccent },
  cardCount: { fontFamily: fonts.bold, fontSize: 14, color: colors.onAccent },
  stamps: { flexDirection: 'row', flexWrap: 'wrap', marginHorizontal: -5, rowGap: 10 },
  slot: { paddingHorizontal: 5 },
  stamp: { aspectRatio: 1, borderRadius: 14, alignItems: 'center', justifyContent: 'center' },
  stampFilled: { backgroundColor: colors.onAccent },
  stampEmpty: { borderWidth: 2, borderStyle: 'dashed', borderColor: colors.onAccent },
  reward: { backgroundColor: colors.primary },
  cardText: { fontFamily: fonts.semibold, fontSize: 15, lineHeight: 21, color: colors.onAccent },
  coupon: { flexDirection: 'row', alignItems: 'center', gap: 14, padding: 14, borderRadius: 16, backgroundColor: colors.surface, borderWidth: 1, borderStyle: 'dashed', borderColor: colors.accent },
  badge: { width: 56, height: 56, borderRadius: 12, backgroundColor: colors.accentSoft, alignItems: 'center', justifyContent: 'center' },
  badgeText: { fontFamily: fonts.bold, fontSize: 15, color: colors.accent },
  couponText: { flex: 1, gap: 2 },
  couponTitle: { fontFamily: fonts.bold, fontSize: 15, color: colors.text },
  automatic: { fontFamily: fonts.bold, fontSize: 13, color: colors.accent },
  use: { minHeight: minTouchSize, paddingHorizontal: 14, borderRadius: 12, backgroundColor: colors.accent, alignItems: 'center', justifyContent: 'center' },
  useLabel: { fontFamily: fonts.bold, fontSize: 14, color: colors.onAccent },
});
