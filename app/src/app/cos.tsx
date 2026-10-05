import { Image } from 'expo-image';
import { Link, router } from 'expo-router';
import { useRef, useState } from 'react';
import { Pressable, ScrollView, StyleSheet, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

import { useConfig, useCreateOrder } from '@/api/hooks';
import { Icon } from '@/components/icon';
import { AppText, Button, Centered, Field, Segmented } from '@/components/ui';
import { colors, fonts, minTouchSize } from '@/constants/theme';
import { deliveryFeeBani, subtotalBani } from '@/lib/cart';
import { randomId } from '@/lib/id';
import { resolveLocation } from '@/lib/location';
import { formatBani, toBani } from '@/lib/money';
import { useCart } from '@/store/cart';
import { useSession } from '@/store/session';

export default function CartScreen() {
  const cart = useCart();
  const config = useConfig();
  const customer = useSession((s) => s.customer);
  const createOrder = useCreateOrder();

  // Untouched, the field follows the account's phone, including after logging in from this screen.
  const [phoneInput, setPhone] = useState<string | null>(null);
  const phone = phoneInput ?? customer?.phone ?? '';
  const [errors, setErrors] = useState<Record<string, string>>({});
  // Same ID for every retry of this checkout, so a timeout never creates a duplicate order.
  const clientOrderId = useRef(randomId());

  const location = resolveLocation(config.data, cart.locationId);
  const paymentMethod = config.data?.payment_methods[0];
  const isDelivery = cart.fulfillment === 'delivery';

  if (cart.lines.length === 0) {
    return (
      <Centered>
        <AppText variant="title">Coșul e gol</AppText>
        <AppText style={{ textAlign: 'center' }}>Alege ceva bun din meniu.</AppText>
        <Button label="Înapoi la meniu" variant="outline" onPress={() => router.back()} />
      </Centered>
    );
  }

  const subtotal = subtotalBani(cart.lines);
  const threshold = toBani(location?.free_delivery_threshold ?? 0);
  const shipping = isDelivery ? deliveryFeeBani(subtotal, toBani(location?.delivery_fee ?? 0), threshold) : 0;
  const total = subtotal + shipping;

  const submit = () => {
    if (!customer) {
      router.push('/autentificare');
      return;
    }

    const found: Record<string, string> = {};
    if (!location) found.location = 'Alege punctul de lucru.';
    if (isDelivery && !cart.address.address_1.trim()) found.address_1 = 'Scrie adresa de livrare.';
    if (isDelivery && !cart.address.city.trim()) found.city = 'Scrie localitatea.';
    if (phone.replace(/\D/g, '').length < 10) found.phone = 'Scrie un număr de telefon valid.';
    if (!paymentMethod) found.payment = 'Plata nu este disponibilă momentan.';
    setErrors(found);
    if (Object.keys(found).length > 0 || !location || !paymentMethod) {
      return;
    }

    createOrder.mutate(
      {
        client_order_id: clientOrderId.current,
        location_id: location.id,
        fulfillment: cart.fulfillment,
        address: isDelivery ? cart.address : undefined,
        phone: phone.trim(),
        note: cart.note.trim(),
        payment_method: paymentMethod.id,
        items: cart.lines.map((l) => ({
          product_id: l.productId,
          variation_id: l.variationId || undefined,
          quantity: l.quantity,
          preferences: l.preferences || undefined,
        })),
      },
      {
        onSuccess: (order) => {
          cart.clear();
          clientOrderId.current = randomId();
          router.replace(`/comanda/${order.id}`);
        },
      },
    );
  };

  return (
    <SafeAreaView edges={['top']} style={styles.screen}>
      <ScrollView contentContainerStyle={styles.content} keyboardShouldPersistTaps="handled">
        <View style={styles.titleRow}>
          <Pressable accessibilityRole="button" accessibilityLabel="Înapoi la meniu" onPress={() => router.back()} style={styles.back}>
            <Icon name="back" color={colors.text} />
          </Pressable>
          <AppText variant="title" style={{ fontSize: 26 }}>
            Coșul tău
          </AppText>
        </View>

        <Segmented
          value={cart.fulfillment}
          onChange={cart.setFulfillment}
          options={[
            { value: 'delivery', label: 'Livrare', disabled: location ? !location.delivery : false },
            { value: 'pickup', label: 'Ridicare', disabled: location ? !location.pickup : false },
          ]}
        />

        <View style={{ gap: 10 }}>
          {cart.lines.map((line) => (
            <View key={line.key} style={styles.line}>
              <Image source={line.imageUrl} contentFit="cover" style={styles.lineImage} />
              <View style={{ flex: 1, gap: 2 }}>
                <AppText variant="bodyStrong">
                  {line.quantity} × {line.name}
                </AppText>
                {line.detail || line.preferences ? <AppText variant="muted">{[line.detail, line.preferences].filter(Boolean).join(' · ')}</AppText> : null}
                <View style={styles.lineControls}>
                  <Pressable accessibilityRole="button" accessibilityLabel={`Scade ${line.name}`} onPress={() => cart.setQuantity(line.key, line.quantity - 1)} style={styles.smallButton}>
                    <Icon name="minus" size={18} color={colors.text} />
                  </Pressable>
                  <Pressable accessibilityRole="button" accessibilityLabel={`Crește ${line.name}`} onPress={() => cart.setQuantity(line.key, line.quantity + 1)} style={styles.smallButton}>
                    <Icon name="plus" size={18} color={colors.text} />
                  </Pressable>
                </View>
              </View>
              <AppText variant="bodyStrong" style={{ fontFamily: fonts.bold }}>
                {formatBani(toBani(line.unitPrice) * line.quantity)}
              </AppText>
            </View>
          ))}
        </View>

        {isDelivery && threshold > 0 ? (
          <View style={styles.card}>
            <AppText variant="bodyStrong" style={{ fontSize: 14 }}>
              {subtotal >= threshold ? 'Ai livrare gratuită' : `Mai ai ${formatBani(threshold - subtotal)} până la livrare gratuită`}
            </AppText>
            <View style={styles.progressTrack}>
              <View style={[styles.progressFill, { width: `${Math.min(100, Math.round((subtotal / threshold) * 100))}%` }]} />
            </View>
          </View>
        ) : null}

        <Link href="/locatie" asChild>
          <Pressable accessibilityRole="button" style={styles.locationRow}>
            <Icon name="pin" size={20} color={colors.accent} />
            <View style={{ flex: 1 }}>
              <AppText variant="bodyStrong">{location?.name ?? 'Alege punctul de lucru'}</AppText>
              <AppText variant="muted">{isDelivery ? 'Livrează acest punct de lucru' : location?.address || 'Ridici de aici'}</AppText>
            </View>
            <AppText style={styles.change}>Schimbă</AppText>
          </Pressable>
        </Link>
        {errors.location ? <AppText style={styles.error}>{errors.location}</AppText> : null}

        {isDelivery ? (
          <View style={{ gap: 12 }}>
            <Field label="Adresa de livrare" placeholder="Strada, numărul" value={cart.address.address_1} onChangeText={(address_1) => cart.setAddress({ address_1 })} autoComplete="street-address" error={errors.address_1} />
            <Field label="Bloc, scară, apartament (opțional)" value={cart.address.address_2} onChangeText={(address_2) => cart.setAddress({ address_2 })} />
            <Field label="Localitatea" value={cart.address.city} onChangeText={(city) => cart.setAddress({ city })} error={errors.city} />
          </View>
        ) : null}

        <Field label="Telefon" placeholder="07xx xxx xxx" value={phone} onChangeText={setPhone} keyboardType="phone-pad" autoComplete="tel" error={errors.phone} />
        <Field label="Observații pentru comandă" placeholder="De exemplu: sunați la interfon" value={cart.note} onChangeText={cart.setNote} multiline maxLength={300} />

        <View style={{ gap: 8 }}>
          <AppText variant="bodyStrong" style={{ fontSize: 14 }}>
            Plata
          </AppText>
          <View style={styles.payment}>
            <AppText variant="bodyStrong">{isDelivery ? 'Numerar la livrare' : 'Numerar la ridicare'}</AppText>
          </View>
          {errors.payment ? <AppText style={styles.error}>{errors.payment}</AppText> : null}
        </View>
      </ScrollView>

      <SafeAreaView edges={['bottom']} style={styles.footer}>
        <Row label="Produse" value={formatBani(subtotal)} />
        {isDelivery ? <Row label="Livrare" value={shipping === 0 ? 'Gratuit' : formatBani(shipping)} /> : null}
        <View style={styles.totalRow}>
          <AppText style={styles.total}>Total</AppText>
          <AppText style={styles.total}>{formatBani(total)}</AppText>
        </View>
        {Object.keys(errors).length > 0 ? <AppText style={styles.error}>Verifică datele marcate mai sus.</AppText> : null}
        {createOrder.isError ? <AppText style={styles.error}>{createOrder.error.message}</AppText> : null}
        <Button label={customer ? 'Trimite comanda' : 'Intră în cont ca să comanzi'} loading={createOrder.isPending} onPress={submit} />
      </SafeAreaView>
    </SafeAreaView>
  );
}

function Row({ label, value }: { label: string; value: string }) {
  return (
    <View style={styles.row}>
      <AppText variant="muted" style={{ fontSize: 14 }}>
        {label}
      </AppText>
      <AppText variant="muted" style={{ fontSize: 14 }}>
        {value}
      </AppText>
    </View>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: colors.background },
  content: { padding: 20, paddingBottom: 40, gap: 14 },
  titleRow: { flexDirection: 'row', alignItems: 'center', gap: 12 },
  back: { width: minTouchSize, height: minTouchSize, borderRadius: 22, backgroundColor: colors.surface, alignItems: 'center', justifyContent: 'center' },
  line: { flexDirection: 'row', alignItems: 'flex-start', gap: 12 },
  lineImage: { width: 44, height: 44, borderRadius: 10, backgroundColor: colors.surface },
  lineControls: { flexDirection: 'row', gap: 8, marginTop: 6 },
  smallButton: { width: minTouchSize, height: 36, borderRadius: 10, borderWidth: 1, borderColor: colors.border, alignItems: 'center', justifyContent: 'center' },
  card: { padding: 14, borderRadius: 14, backgroundColor: colors.surface, gap: 8 },
  progressTrack: { height: 8, borderRadius: 4, backgroundColor: colors.border, overflow: 'hidden' },
  progressFill: { height: 8, backgroundColor: colors.accent },
  locationRow: { flexDirection: 'row', alignItems: 'center', gap: 12, padding: 14, borderRadius: 14, borderWidth: 1, borderColor: colors.border },
  change: { fontFamily: fonts.semibold, fontSize: 14, color: colors.accent },
  payment: { minHeight: 48, borderRadius: 12, borderWidth: 2, borderColor: colors.accent, backgroundColor: colors.accentSoft, alignItems: 'center', justifyContent: 'center' },
  error: { fontFamily: fonts.medium, fontSize: 13, color: '#FF8A75' },
  footer: { paddingHorizontal: 20, paddingTop: 14, paddingBottom: 14, gap: 8, backgroundColor: colors.bar, borderTopWidth: 1, borderTopColor: colors.barBorder },
  row: { flexDirection: 'row', justifyContent: 'space-between' },
  totalRow: { flexDirection: 'row', justifyContent: 'space-between', marginTop: 2, marginBottom: 6 },
  total: { fontFamily: fonts.bold, fontSize: 18, color: colors.text },
});
