import { Image } from 'expo-image';
import { Link, router } from 'expo-router';
import { useMemo, useState } from 'react';
import { ActivityIndicator, Pressable, ScrollView, SectionList, StyleSheet, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

import { useConfig, useLoyalty, useMenu } from '@/api/hooks';
import type { Product } from '@/api/types';
import { CartBar } from '@/components/cart-bar';
import { Icon } from '@/components/icon';
import { AppText, Centered, Chip, LoadError } from '@/components/ui';
import { colors, fonts, minTouchSize } from '@/constants/theme';
import { stampsLeftLabel } from '@/lib/cart';
import { productSubtitle } from '@/lib/format';
import { stampSlots } from '@/lib/loyalty';
import { resolveLocation } from '@/lib/location';
import { formatPrice } from '@/lib/money';
import { useCart } from '@/store/cart';

const ALL = 0;

export default function MenuScreen() {
  const menu = useMenu();
  const config = useConfig();
  const loyalty = useLoyalty();
  const [categoryId, setCategoryId] = useState(ALL);
  const { fulfillment, locationId, add } = useCart();
  const location = resolveLocation(config.data, locationId);

  const sections = useMemo(() => {
    if (!menu.data) {
      return [];
    }
    const byId = new Map(menu.data.products.map((p) => [p.id, p]));
    return menu.data.categories
      .filter((c) => categoryId === ALL || c.id === categoryId)
      .map((c) => ({
        key: String(c.id),
        title: c.name,
        data: c.product_ids.map((id) => byId.get(id)).filter((p): p is Product => p !== undefined),
      }))
      .filter((s) => s.data.length > 0);
  }, [menu.data, categoryId]);

  if (menu.isPending) {
    return (
      <Centered>
        <ActivityIndicator color={colors.accent} size="large" />
      </Centered>
    );
  }

  if (menu.isError) {
    return <LoadError message={menu.error.message} onRetry={() => void menu.refetch()} />;
  }

  const quickAdd = (product: Product) => {
    // Products with choices open the detail screen; the rest go straight into the cart.
    if (product.type === 'variable' || product.preference_options.length > 0) {
      router.push(`/produs/${product.id}`);
      return;
    }
    add({
      productId: product.id,
      variationId: 0,
      name: product.name,
      detail: '',
      imageUrl: product.image?.thumbnail ?? null,
      unitPrice: product.price,
      quantity: 1,
      preferences: '',
    });
  };

  return (
    <SafeAreaView edges={['top']} style={styles.screen}>
      <SectionList
        sections={sections}
        keyExtractor={(item) => String(item.id)}
        stickySectionHeadersEnabled={false}
        contentContainerStyle={styles.list}
        ListHeaderComponent={
          <View style={styles.header}>
            <View>
              <AppText variant="title">Micu&apos; lu&apos; Bicu</AppText>
              <AppText style={styles.tagline}>de la Bicu, direct pe jar</AppText>
            </View>

            <Link href="/locatie" asChild>
              <Pressable accessibilityRole="button" style={styles.locationButton}>
                <Icon name="pin" size={20} color={colors.accent} />
                <View style={{ flex: 1 }}>
                  <AppText variant="muted">{fulfillment === 'delivery' ? 'Livrare din punctul' : 'Ridicare de la'}</AppText>
                  <AppText variant="bodyStrong">{location?.name ?? 'Alege punctul de lucru'}</AppText>
                </View>
                <Icon name="chevronDown" size={18} color={colors.text} />
              </Pressable>
            </Link>

            {loyalty.data?.enabled === false ? null : (
              <Link href="/fidelitate" asChild>
                <Pressable accessibilityRole="link" style={styles.loyalty}>
                  <View style={{ flex: 1, gap: 3 }}>
                    <AppText style={styles.loyaltyTitle}>Cardul de fidelitate</AppText>
                    <AppText style={styles.loyaltyText}>
                      {loyalty.data ? stampsLeftLabel(loyalty.data.stamps, loyalty.data.required) : 'La fiecare 4 comenzi primești o reducere'}
                    </AppText>
                  </View>
                  {loyalty.data && loyalty.data.required <= 8 ? (
                    <View style={styles.dots}>
                      {stampSlots(loyalty.data.stamps, loyalty.data.required).map((filled, i) => (
                        <View key={i} style={[styles.dot, filled ? styles.dotFilled : styles.dotEmpty]} />
                      ))}
                    </View>
                  ) : null}
                </Pressable>
              </Link>
            )}

            <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={styles.chips}>
              <Chip kind="filter" label="Toate" selected={categoryId === ALL} onPress={() => setCategoryId(ALL)} />
              {menu.data.categories.map((c) => (
                <Chip key={c.id} kind="filter" label={c.name} selected={categoryId === c.id} onPress={() => setCategoryId(c.id)} />
              ))}
            </ScrollView>
          </View>
        }
        renderSectionHeader={({ section }) => (
          <AppText variant="heading" style={styles.sectionTitle}>
            {section.title}
          </AppText>
        )}
        renderItem={({ item }) => <ProductRow product={item} onAdd={() => quickAdd(item)} />}
        ListEmptyComponent={<AppText style={{ textAlign: 'center' }}>Meniul e gol momentan.</AppText>}
        ListFooterComponent={<View style={{ height: 90 }} />}
        refreshing={menu.isRefetching}
        onRefresh={() => void menu.refetch()}
      />
      <CartBar />
    </SafeAreaView>
  );
}

function ProductRow({ product, onAdd }: { product: Product; onAdd: () => void }) {
  const subtitle = productSubtitle(product);
  return (
    <View style={[styles.row, !product.in_stock && styles.unavailable]}>
      <Link href={`/produs/${product.id}`} asChild>
        <Pressable accessibilityRole="link" style={styles.rowLink}>
          <Image source={product.image?.thumbnail} contentFit="cover" style={styles.rowImage} accessibilityLabel={product.image?.alt || product.name} />
          <View style={styles.rowText}>
            <AppText variant="bodyStrong" style={{ fontFamily: fonts.bold, fontSize: 16 }}>
              {product.name}
            </AppText>
            {subtitle ? (
              <AppText variant="muted" numberOfLines={2}>
                {subtitle}
              </AppText>
            ) : null}
            <AppText variant="price">{product.in_stock ? formatPrice(product.price) : 'Indisponibil'}</AppText>
          </View>
        </Pressable>
      </Link>
      {product.in_stock ? (
        <Pressable accessibilityRole="button" accessibilityLabel={`Adaugă ${product.name} în coș`} onPress={onAdd} style={styles.addButton}>
          <Icon name="plus" size={20} color={colors.onPrimary} strokeWidth={2.4} />
        </Pressable>
      ) : null}
    </View>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: colors.background },
  list: { paddingHorizontal: 20, gap: 10 },
  header: { gap: 14, paddingTop: 22, paddingBottom: 6 },
  tagline: { fontFamily: fonts.semibold, fontSize: 13, color: colors.accent },
  locationButton: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    minHeight: minTouchSize,
    paddingVertical: 12,
    paddingHorizontal: 14,
    borderRadius: 14,
    borderWidth: 1,
    borderColor: colors.border,
    backgroundColor: colors.surface,
  },
  loyalty: { flexDirection: 'row', alignItems: 'center', gap: 12, padding: 16, borderRadius: 16, backgroundColor: colors.accent },
  dots: { flexDirection: 'row', gap: 6 },
  dot: { width: 18, height: 18, borderRadius: 9 },
  dotFilled: { backgroundColor: colors.onAccent },
  dotEmpty: { borderWidth: 2, borderColor: colors.onAccent },
  loyaltyTitle: { fontFamily: fonts.heading, fontSize: 18, color: colors.onAccent },
  loyaltyText: { fontFamily: fonts.medium, fontSize: 13, color: colors.onAccent },
  chips: { gap: 8, paddingVertical: 4 },
  sectionTitle: { marginTop: 12, marginBottom: 2 },
  row: { flexDirection: 'row', alignItems: 'center', gap: 12, padding: 10, borderRadius: 16, backgroundColor: colors.surface },
  unavailable: { opacity: 0.55 },
  rowLink: { flex: 1, flexDirection: 'row', alignItems: 'center', gap: 12 },
  rowImage: { width: 80, height: 80, borderRadius: 12, backgroundColor: colors.border },
  rowText: { flex: 1, gap: 4 },
  addButton: { width: minTouchSize, height: minTouchSize, borderRadius: 22, backgroundColor: colors.primary, alignItems: 'center', justifyContent: 'center' },
});
