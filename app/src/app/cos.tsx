import { Image } from 'expo-image';
import { Link, router } from 'expo-router';
import { useMemo, useRef, useState } from 'react';
import { ActivityIndicator, Pressable, ScrollView, StyleSheet, TextInput, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

import { previewCart, useCartPreview, useConfig, useCreateGuestOrder, useCreateOrder } from '@/api/hooks';
import type { CartPreviewRequest, NewOrder } from '@/api/types';
import { Icon } from '@/components/icon';
import { AppText, Button, Centered, Field, Segmented } from '@/components/ui';
import { colors, fonts, minTouchSize } from '@/constants/theme';
import { addCouponCode, deliveryFeeBani, orderItems, subtotalBani } from '@/lib/cart';
import { guestContactErrors, type GuestContact } from '@/lib/guest';
import { randomId } from '@/lib/id';
import { registerForPush } from '@/lib/push';
import { resolveLocation } from '@/lib/location';
import { formatBani, toBani } from '@/lib/money';
import { paymentLabel } from '@/lib/payment';
import { useCart } from '@/store/cart';
import { useGuestOrders } from '@/store/guest-orders';
import { useSession } from '@/store/session';

export default function CartScreen() {
  const cart = useCart();
  const config = useConfig();
  const customer = useSession((s) => s.customer);
  const createOrder = useCreateOrder();
  const createGuestOrder = useCreateGuestOrder();

  // Without an account the customer can still order with just their details, when the restaurant allows it.
  const asGuest = !customer && config.data?.guest_checkout === true;
  const placing = customer ? createOrder : createGuestOrder;
  const savedContact = useGuestOrders((s) => s.contact);
  const [contact, setContact] = useState<Omit<GuestContact, 'phone'>>(savedContact);
  const updateContact = (change: Partial<GuestContact>) => setContact((current) => ({ ...current, ...change }));

  // Untouched, the field follows the account's phone, including after logging in from this screen.
  const [phoneInput, setPhone] = useState<string | null>(null);
  const phone = phoneInput ?? customer?.phone ?? savedContact.phone;
  const [errors, setErrors] = useState<Record<string, string>>({});
  // Same ID for every retry of this checkout, so a timeout never creates a duplicate order.
  const clientOrderId = useRef(randomId());

  const location = resolveLocation(config.data, cart.locationId);
  const hasLocationChoice = (config.data?.locations.length ?? 0) > 1;
  const paymentMethods = config.data?.payment_methods ?? [];
  const [paymentId, setPaymentId] = useState<string | null>(null);
  const paymentMethod = paymentMethods.find((m) => m.id === paymentId) ?? paymentMethods[0];
  const isDelivery = cart.fulfillment === 'delivery';

  const [codeInput, setCodeInput] = useState('');
  const [codeError, setCodeError] = useState<string | null>(null);
  const [checkingCode, setCheckingCode] = useState(false);
  const previewRequest = useMemo<CartPreviewRequest>(
    () => ({ items: orderItems(cart.lines), coupon_codes: cart.couponCodes, fulfillment: cart.fulfillment, location_id: location?.id ?? '' }),
    [cart.lines, cart.couponCodes, cart.fulfillment, location?.id],
  );
  const preview = useCartPreview(previewRequest);
  // A code that stopped applying (e.g. the cart dropped under its minimum) blocks the order until removed.
  const couponProblem = cart.couponCodes.length > 0 && preview.isError ? preview.error.message : null;

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
  const hasDiscount = cart.couponCodes.length > 0 && preview.data !== undefined && !preview.isError;
  const discount = hasDiscount ? toBani(preview.data!.discount) : 0;
  const total = Math.max(0, subtotal - discount) + shipping;

  const applyCode = async () => {
    const codes = addCouponCode(cart.couponCodes, codeInput);
    if (codes === cart.couponCodes) {
      setCodeInput('');
      return;
    }
    setCheckingCode(true);
    setCodeError(null);
    try {
      await previewCart({ ...previewRequest, coupon_codes: codes });
      cart.addCoupon(codeInput);
      setCodeInput('');
    } catch (error) {
      setCodeError(error instanceof Error ? error.message : 'Codul nu a putut fi verificat.');
    } finally {
      setCheckingCode(false);
    }
  };

  const submit = () => {
    if (!customer && !asGuest) {
      router.push('/autentificare');
      return;
    }

    const found: Record<string, string> = asGuest ? { ...guestContactErrors({ ...contact, phone }) } : {};
    if (!location) found.location = 'Alege punctul de lucru.';
    if (isDelivery && !cart.address.address_1.trim()) found.address_1 = 'Scrie adresa de livrare.';
    if (isDelivery && !cart.address.city.trim()) found.city = 'Scrie localitatea.';
    if (phone.replace(/\D/g, '').length < 10) found.phone = 'Scrie un număr de telefon valid.';
    if (!paymentMethod) found.payment = 'Plata nu este disponibilă momentan.';
    if (couponProblem) found.coupon = couponProblem;
    setErrors(found);
    if (Object.keys(found).length > 0 || !location || !paymentMethod) {
      return;
    }

    const order: NewOrder = {
      client_order_id: clientOrderId.current,
      location_id: location.id,
      fulfillment: cart.fulfillment,
      address: isDelivery ? cart.address : undefined,
      phone: phone.trim(),
      note: cart.note.trim(),
      payment_method: paymentMethod.id,
      items: orderItems(cart.lines),
      coupon_codes: cart.couponCodes,
    };
    const placed = (orderId: number) => {
      cart.clear();
      clientOrderId.current = randomId();
      router.replace(`/comanda/${orderId}`);
    };

    if (customer) {
      createOrder.mutate(order, {
        onSuccess: ({ id }) => {
          placed(id);
          // Right after ordering is when asking for notification permission makes sense.
          void registerForPush({ ask: true });
        },
      });
      return;
    }

    const guestContact = { first_name: contact.first_name.trim(), last_name: contact.last_name.trim(), email: contact.email.trim() };
    createGuestOrder.mutate(
      { ...order, ...guestContact },
      {
        onSuccess: ({ id, guest_token }) => {
          useGuestOrders.getState().setContact({ ...guestContact, phone: phone.trim() });
          placed(id);
          void registerForPush({ ask: true, guestOrder: { id, token: guest_token } });
        },
      },
    );
  };

  const locationDetails = (
    <>
      <Icon name="pin" size={20} color={colors.accent} />
      <View style={{ flex: 1 }}>
        <AppText variant="bodyStrong">{location?.name ?? 'Alege punctul de lucru'}</AppText>
        <AppText variant="muted">{isDelivery ? 'Livrează acest punct de lucru' : location?.address || 'Ridici de aici'}</AppText>
      </View>
    </>
  );

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

        {hasLocationChoice ? (
          <Link href="/locatie" asChild>
            <Pressable accessibilityRole="button" style={styles.locationRow}>
              {locationDetails}
              <AppText style={styles.change}>Schimbă</AppText>
            </Pressable>
          </Link>
        ) : (
          <View style={styles.locationRow}>{locationDetails}</View>
        )}
        {errors.location ? <AppText style={styles.error}>{errors.location}</AppText> : null}

        {asGuest ? (
          <View style={styles.guestCard}>
            <View style={{ flex: 1, gap: 2 }}>
              <AppText variant="bodyStrong">Comanzi fără cont</AppText>
              <AppText variant="muted">Ai nevoie doar de nume și telefon. Cu un cont strângi ștampile pe cardul de fidelitate.</AppText>
            </View>
            <Pressable accessibilityRole="button" onPress={() => router.push('/autentificare')} style={styles.guestLogin}>
              <AppText style={styles.change}>Intră în cont</AppText>
            </Pressable>
          </View>
        ) : null}
        {asGuest ? (
          <View style={{ gap: 12 }}>
            <Field label="Prenume" value={contact.first_name} onChangeText={(first_name) => updateContact({ first_name })} autoComplete="given-name" error={errors.first_name} />
            <Field label="Nume (opțional)" value={contact.last_name} onChangeText={(last_name) => updateContact({ last_name })} autoComplete="family-name" />
            <Field
              label="Email (opțional)"
              value={contact.email}
              onChangeText={(email) => updateContact({ email })}
              keyboardType="email-address"
              autoCapitalize="none"
              autoComplete="email"
              error={errors.email}
            />
          </View>
        ) : null}

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
          <View style={styles.codeRow}>
            <TextInput
              accessibilityLabel="Cod promoțional"
              placeholder="Cod promoțional"
              placeholderTextColor={colors.textTertiary}
              value={codeInput}
              onChangeText={(text) => {
                setCodeInput(text);
                setCodeError(null);
              }}
              onSubmitEditing={() => void applyCode()}
              autoCapitalize="none"
              autoCorrect={false}
              returnKeyType="done"
              style={styles.codeInput}
            />
            <Pressable accessibilityRole="button" onPress={() => void applyCode()} disabled={checkingCode || !codeInput.trim()} style={[styles.codeButton, (checkingCode || !codeInput.trim()) && { opacity: 0.5 }]}>
              {checkingCode ? <ActivityIndicator color={colors.accent} /> : <AppText style={styles.codeButtonLabel}>Aplică</AppText>}
            </Pressable>
          </View>
          {codeError ? <AppText style={styles.error}>{codeError}</AppText> : null}
          {cart.couponCodes.map((code) => {
            const applied = preview.data?.coupons.find((c) => c.code === code);
            return (
              <View key={code} style={styles.appliedCode}>
                <Icon name="gift" size={18} color={colors.accent} />
                <View style={{ flex: 1 }}>
                  <AppText variant="bodyStrong" style={{ fontSize: 14 }}>
                    {code.toUpperCase()}
                  </AppText>
                  {applied ? <AppText variant="muted">{applied.description || `Reducere ${applied.amount_label}`}</AppText> : null}
                </View>
                <Pressable accessibilityRole="button" accessibilityLabel={`Scoate codul ${code}`} onPress={() => cart.removeCoupon(code)} style={styles.removeCode}>
                  <AppText style={styles.change}>Scoate</AppText>
                </Pressable>
              </View>
            );
          })}
          {couponProblem ? <AppText style={styles.error}>{couponProblem}</AppText> : null}
        </View>

        <View style={{ gap: 8 }}>
          <AppText variant="bodyStrong" style={{ fontSize: 14 }}>
            Plata
          </AppText>
          {paymentMethods.length > 1 && paymentMethod ? (
            <Segmented
              value={paymentMethod.id}
              onChange={setPaymentId}
              options={paymentMethods.map((m) => ({ value: m.id, label: paymentLabel(m.id, cart.fulfillment) }))}
            />
          ) : (
            <View style={styles.payment}>
              <AppText variant="bodyStrong">{paymentLabel(paymentMethod?.id ?? 'cod', cart.fulfillment)}</AppText>
            </View>
          )}
          {errors.payment ? <AppText style={styles.error}>{errors.payment}</AppText> : null}
        </View>
      </ScrollView>

      <SafeAreaView edges={['bottom']} style={styles.footer}>
        <Row label="Produse" value={formatBani(subtotal)} />
        {isDelivery ? <Row label="Livrare" value={shipping === 0 ? 'Gratuit' : formatBani(shipping)} /> : null}
        {discount > 0 ? <Row label="Reducere" value={`−${formatBani(discount)}`} highlight /> : null}
        <View style={styles.totalRow}>
          <AppText style={styles.total}>Total</AppText>
          <AppText style={styles.total}>{formatBani(total)}</AppText>
        </View>
        {Object.keys(errors).length > 0 ? <AppText style={styles.error}>Verifică datele marcate mai sus.</AppText> : null}
        {placing.isError ? <AppText style={styles.error}>{placing.error.message}</AppText> : null}
        <Button label={customer || asGuest ? 'Trimite comanda' : 'Intră în cont ca să comanzi'} loading={placing.isPending} onPress={submit} />
      </SafeAreaView>
    </SafeAreaView>
  );
}

function Row({ label, value, highlight }: { label: string; value: string; highlight?: boolean }) {
  const style = [{ fontSize: 14 }, highlight && { color: colors.accent, fontFamily: fonts.semibold }];
  return (
    <View style={styles.row}>
      <AppText variant="muted" style={style}>
        {label}
      </AppText>
      <AppText variant="muted" style={style}>
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
  guestCard: { flexDirection: 'row', alignItems: 'center', gap: 12, padding: 14, borderRadius: 14, backgroundColor: colors.surface },
  guestLogin: { minHeight: minTouchSize, justifyContent: 'center' },
  locationRow: { flexDirection: 'row', alignItems: 'center', gap: 12, padding: 14, borderRadius: 14, borderWidth: 1, borderColor: colors.border },
  change: { fontFamily: fonts.semibold, fontSize: 14, color: colors.accent },
  payment: { minHeight: 48, borderRadius: 12, borderWidth: 2, borderColor: colors.accent, backgroundColor: colors.accentSoft, alignItems: 'center', justifyContent: 'center' },
  codeRow: { flexDirection: 'row', gap: 8 },
  codeInput: { flex: 1, height: 46, paddingHorizontal: 14, borderRadius: 12, borderWidth: 1, borderColor: colors.border, backgroundColor: colors.surface, color: colors.text, fontFamily: fonts.regular, fontSize: 14 },
  codeButton: { height: 46, minWidth: 80, paddingHorizontal: 18, borderRadius: 12, borderWidth: 1, borderColor: colors.accent, alignItems: 'center', justifyContent: 'center' },
  codeButtonLabel: { fontFamily: fonts.bold, fontSize: 14, color: colors.accent },
  appliedCode: { flexDirection: 'row', alignItems: 'center', gap: 12, paddingLeft: 14, borderRadius: 12, borderWidth: 1, borderStyle: 'dashed', borderColor: colors.accent, backgroundColor: colors.surface },
  removeCode: { minHeight: minTouchSize, paddingHorizontal: 14, justifyContent: 'center' },
  error: { fontFamily: fonts.medium, fontSize: 13, color: colors.error },
  footer: { paddingHorizontal: 20, paddingTop: 14, paddingBottom: 14, gap: 8, backgroundColor: colors.bar, borderTopWidth: 1, borderTopColor: colors.barBorder },
  row: { flexDirection: 'row', justifyContent: 'space-between' },
  totalRow: { flexDirection: 'row', justifyContent: 'space-between', marginTop: 2, marginBottom: 6 },
  total: { fontFamily: fonts.bold, fontSize: 18, color: colors.text },
});
