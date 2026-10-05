import { Image } from 'expo-image';
import { router, useLocalSearchParams } from 'expo-router';
import { useState } from 'react';
import { ActivityIndicator, Pressable, ScrollView, StyleSheet, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

import { useMenu } from '@/api/hooks';
import { Icon } from '@/components/icon';
import { AppText, Button, Centered, Chip, Field, LoadError, Stepper } from '@/components/ui';
import { colors, minTouchSize } from '@/constants/theme';
import { joinPreferences } from '@/lib/cart';
import { formatWeight } from '@/lib/format';
import { formatBani, toBani } from '@/lib/money';
import { useCart } from '@/store/cart';

export default function ProductScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const menu = useMenu();
  const add = useCart((s) => s.add);

  const product = menu.data?.products.find((p) => p.id === Number(id));
  const [variationId, setVariationId] = useState<number | null>(null);
  const [chips, setChips] = useState<string[]>([]);
  const [note, setNote] = useState('');
  const [quantity, setQuantity] = useState(1);

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
  if (!product) {
    return (
      <Centered>
        <AppText variant="bodyStrong">Produsul nu mai este în meniu.</AppText>
        <Button label="Înapoi la meniu" variant="outline" onPress={() => router.back()} />
      </Centered>
    );
  }

  const variation = product.variations.find((v) => v.id === variationId) ?? null;
  const needsVariation = product.type === 'variable' && variation === null;
  const unitPrice = variation?.price ?? product.price;
  const available = variation ? variation.in_stock : product.in_stock;
  const weight = formatWeight(product.weight);

  const toggleChip = (option: string) => {
    setChips((current) => (current.includes(option) ? current.filter((c) => c !== option) : [...current, option]));
  };

  const addToCart = () => {
    add({
      productId: product.id,
      variationId: variation?.id ?? 0,
      name: product.name,
      detail: variation?.name ?? '',
      imageUrl: product.image?.thumbnail ?? null,
      unitPrice,
      quantity,
      preferences: joinPreferences(chips, note),
    });
    router.back();
  };

  return (
    <View style={styles.screen}>
      <ScrollView contentContainerStyle={{ paddingBottom: 120 }}>
        <View>
          <Image source={product.image?.url} contentFit="cover" style={styles.hero} accessibilityLabel={product.image?.alt || product.name} />
          <SafeAreaView edges={['top']} style={styles.backWrap}>
            <Pressable accessibilityRole="button" accessibilityLabel="Înapoi la meniu" onPress={() => router.back()} style={styles.back}>
              <Icon name="back" color={colors.text} />
            </Pressable>
          </SafeAreaView>
        </View>

        <View style={styles.body}>
          <View style={styles.titleRow}>
            <AppText variant="title" style={{ flex: 1 }}>
              {product.name}
            </AppText>
            <AppText variant="price" style={{ fontSize: 20 }}>
              {formatBani(toBani(unitPrice))}
            </AppText>
          </View>

          {weight ? (
            <View style={styles.tags}>
              <AppText variant="bodyStrong" style={styles.tag}>
                {weight}
              </AppText>
            </View>
          ) : null}

          {product.description || product.short_description ? <AppText>{product.description || product.short_description}</AppText> : null}

          {product.type === 'variable' ? (
            <View style={styles.group}>
              <AppText variant="heading" style={{ fontSize: 19 }}>
                Alege varianta
              </AppText>
              <View style={styles.chipWrap}>
                {product.variations.map((v) => (
                  <Chip key={v.id} label={`${v.name} · ${formatBani(toBani(v.price))}`} selected={v.id === variationId} onPress={() => setVariationId(v.id)} />
                ))}
              </View>
            </View>
          ) : null}

          {product.preference_options.length > 0 ? (
            <View style={styles.group}>
              <AppText variant="heading" style={{ fontSize: 19 }}>
                Preferințe
              </AppText>
              <View style={styles.chipWrap}>
                {product.preference_options.map((option) => (
                  <Chip key={option} label={option} selected={chips.includes(option)} onPress={() => toggleChip(option)} />
                ))}
              </View>
            </View>
          ) : null}

          <Field label="Observații pentru bucătărie" placeholder="De exemplu: muștarul separat" value={note} onChangeText={setNote} multiline maxLength={150} />
        </View>
      </ScrollView>

      <SafeAreaView edges={['bottom']} style={styles.footer}>
        <Stepper value={quantity} onChange={setQuantity} />
        <View style={{ flex: 1 }}>
          <Button
            label={needsVariation ? 'Alege varianta' : available ? `Adaugă în coș · ${formatBani(toBani(unitPrice) * quantity)}` : 'Indisponibil'}
            disabled={needsVariation || !available}
            onPress={addToCart}
          />
        </View>
      </SafeAreaView>
    </View>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: colors.background },
  hero: { width: '100%', height: 300, backgroundColor: colors.surface },
  backWrap: { position: 'absolute', top: 0, left: 20 },
  back: { marginTop: 12, width: minTouchSize, height: minTouchSize, borderRadius: 22, backgroundColor: 'rgba(23,18,15,0.85)', alignItems: 'center', justifyContent: 'center' },
  body: { marginTop: -24, borderTopLeftRadius: 24, borderTopRightRadius: 24, backgroundColor: colors.background, padding: 20, gap: 14 },
  titleRow: { flexDirection: 'row', alignItems: 'baseline', gap: 12 },
  tags: { flexDirection: 'row', gap: 8 },
  tag: { paddingVertical: 6, paddingHorizontal: 10, borderRadius: 8, backgroundColor: colors.surface, fontSize: 13, overflow: 'hidden' },
  group: { gap: 10 },
  chipWrap: { flexDirection: 'row', flexWrap: 'wrap', gap: 8 },
  footer: {
    position: 'absolute',
    left: 0,
    right: 0,
    bottom: 0,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    paddingHorizontal: 16,
    paddingTop: 14,
    paddingBottom: 14,
    backgroundColor: colors.bar,
    borderTopWidth: 1,
    borderTopColor: colors.barBorder,
  },
});
