import { router } from 'expo-router';
import { ActivityIndicator, Pressable, ScrollView, StyleSheet, View } from 'react-native';

import { useConfig } from '@/api/hooks';
import { Icon } from '@/components/icon';
import { AppText, Centered, LoadError, Segmented } from '@/components/ui';
import { colors } from '@/constants/theme';
import { resolveLocation } from '@/lib/location';
import { useCart } from '@/store/cart';

export default function LocationScreen() {
  const config = useConfig();
  const { fulfillment, setFulfillment, locationId, setLocationId } = useCart();

  if (config.isPending) {
    return (
      <Centered>
        <ActivityIndicator color={colors.accent} size="large" />
      </Centered>
    );
  }
  if (config.isError) {
    return <LoadError message={config.error.message} onRetry={() => void config.refetch()} />;
  }

  const selected = resolveLocation(config.data, locationId);

  return (
    <ScrollView style={styles.screen} contentContainerStyle={styles.content}>
      <AppText variant="title">Cum vrei comanda?</AppText>
      <Segmented
        value={fulfillment}
        onChange={setFulfillment}
        options={[
          { value: 'delivery', label: 'Livrare' },
          { value: 'pickup', label: 'Ridicare' },
        ]}
      />

      <AppText variant="heading">Punctul de lucru</AppText>
      {config.data.locations.map((location) => {
        const offers = fulfillment === 'delivery' ? location.delivery : location.pickup;
        const isSelected = location.id === selected?.id;
        return (
          <Pressable
            key={location.id}
            accessibilityRole="radio"
            accessibilityState={{ checked: isSelected, disabled: !offers }}
            disabled={!offers}
            onPress={() => {
              setLocationId(location.id);
              router.back();
            }}
            style={[styles.option, isSelected && styles.optionSelected, !offers && { opacity: 0.5 }]}>
            <Icon name="pin" size={20} color={colors.accent} />
            <View style={{ flex: 1, gap: 2 }}>
              <AppText variant="bodyStrong">{location.name}</AppText>
              {location.address ? <AppText variant="muted">{location.address}</AppText> : null}
              {!offers ? <AppText variant="muted">{fulfillment === 'delivery' ? 'Nu livrează momentan' : 'Ridicarea nu e disponibilă'}</AppText> : null}
            </View>
            {isSelected ? <Icon name="check" size={20} color={colors.accent} strokeWidth={3} /> : null}
          </Pressable>
        );
      })}
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: colors.background },
  content: { padding: 20, gap: 14 },
  option: { flexDirection: 'row', alignItems: 'center', gap: 12, padding: 16, borderRadius: 14, borderWidth: 1, borderColor: colors.border, backgroundColor: colors.surface },
  optionSelected: { borderWidth: 2, borderColor: colors.accent },
});
