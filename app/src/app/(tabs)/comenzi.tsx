import { Link, router } from 'expo-router';
import { ActivityIndicator, FlatList, Pressable, StyleSheet, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

import { useMenu, useOrders } from '@/api/hooks';
import type { Order } from '@/api/types';
import { AppText, Button, Centered, LoadError } from '@/components/ui';
import { colors, fonts } from '@/constants/theme';
import { formatPrice } from '@/lib/money';
import { isActive } from '@/lib/status';
import { useCart } from '@/store/cart';
import { useSession } from '@/store/session';

export default function OrdersScreen() {
  const token = useSession((s) => s.token);
  const orders = useOrders();
  const menu = useMenu();
  const add = useCart((s) => s.add);

  if (!token) {
    return (
      <Centered>
        <AppText variant="title">Comenzile tale</AppText>
        <AppText style={{ textAlign: 'center' }}>Intră în cont ca să vezi comenzile și să comanzi din nou.</AppText>
        <Button label="Intră în cont" onPress={() => router.push('/autentificare')} />
      </Centered>
    );
  }
  if (orders.isPending) {
    return (
      <Centered>
        <ActivityIndicator color={colors.accent} size="large" />
      </Centered>
    );
  }
  if (orders.isError) {
    return <LoadError message={orders.error.message} onRetry={() => void orders.refetch()} />;
  }

  // Re-adds the items still on the menu, at today's prices.
  const reorder = (order: Order) => {
    const products = new Map(menu.data?.products.map((p) => [p.id, p]));
    let added = 0;
    for (const item of order.items) {
      const product = products.get(item.product_id);
      const variation = item.variation_id ? product?.variations.find((v) => v.id === item.variation_id) : undefined;
      if (!product || (item.variation_id && !variation) || !(variation?.in_stock ?? product.in_stock)) {
        continue;
      }
      add({
        productId: product.id,
        variationId: variation?.id ?? 0,
        name: product.name,
        detail: variation?.name ?? '',
        imageUrl: product.image?.thumbnail ?? null,
        unitPrice: variation?.price ?? product.price,
        quantity: item.quantity,
        preferences: item.preferences,
      });
      added += 1;
    }
    if (added > 0) {
      router.push('/cos');
    }
  };

  return (
    <SafeAreaView edges={['top']} style={styles.screen}>
      <FlatList
        data={orders.data}
        keyExtractor={(order) => String(order.id)}
        contentContainerStyle={styles.content}
        ListHeaderComponent={<AppText variant="title">Comenzile tale</AppText>}
        ListEmptyComponent={<AppText>Nu ai încă nicio comandă.</AppText>}
        refreshing={orders.isRefetching}
        onRefresh={() => void orders.refetch()}
        renderItem={({ item: order }) => (
          <View style={styles.card}>
            <Link href={`/comanda/${order.id}`} asChild>
              <Pressable accessibilityRole="link" style={{ gap: 4 }}>
                <View style={styles.cardTop}>
                  <AppText variant="bodyStrong">Comanda #{order.number}</AppText>
                  <AppText style={[styles.status, isActive(order.status) && { color: colors.accent }]}>{order.status_label}</AppText>
                </View>
                <AppText variant="muted" numberOfLines={2}>
                  {order.items.map((i) => `${i.quantity} × ${i.name}`).join(', ')}
                </AppText>
                <AppText variant="muted">
                  {order.created_at ? new Date(order.created_at).toLocaleDateString('ro-RO', { day: 'numeric', month: 'long', year: 'numeric' }) : ''} · {formatPrice(order.total)}
                </AppText>
              </Pressable>
            </Link>
            <Button label="Comandă din nou" variant="outline" disabled={!menu.data} onPress={() => reorder(order)} />
          </View>
        )}
      />
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: colors.background },
  content: { padding: 20, gap: 12 },
  card: { padding: 14, borderRadius: 16, backgroundColor: colors.surface, gap: 12 },
  cardTop: { flexDirection: 'row', justifyContent: 'space-between', gap: 12 },
  status: { fontFamily: fonts.semibold, fontSize: 13, color: colors.textMuted },
});
