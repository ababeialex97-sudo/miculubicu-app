import { Link } from 'expo-router';
import { Pressable, StyleSheet, Text, View } from 'react-native';

import { colors, fonts } from '@/constants/theme';
import { itemCount, subtotalBani } from '@/lib/cart';
import { formatBani } from '@/lib/money';
import { useCart } from '@/store/cart';

export function CartBar() {
  const lines = useCart((s) => s.lines);
  if (lines.length === 0) {
    return null;
  }

  const count = itemCount(lines);

  return (
    <Link href="/cos" asChild>
      <Pressable accessibilityRole="button" accessibilityLabel={`Vezi coșul, ${count} produse, ${formatBani(subtotalBani(lines))}`} style={styles.bar}>
        <View style={styles.left}>
          <View style={styles.badge}>
            <Text style={styles.badgeText}>{count}</Text>
          </View>
          <Text style={styles.label}>Vezi coșul</Text>
        </View>
        <Text style={styles.label}>{formatBani(subtotalBani(lines))}</Text>
      </Pressable>
    </Link>
  );
}

const styles = StyleSheet.create({
  bar: {
    position: 'absolute',
    left: 16,
    right: 16,
    bottom: 12,
    height: 58,
    borderRadius: 18,
    backgroundColor: colors.primary,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: 18,
    shadowColor: '#000',
    shadowOpacity: 0.5,
    shadowRadius: 15,
    shadowOffset: { width: 0, height: 10 },
    elevation: 8,
  },
  left: { flexDirection: 'row', alignItems: 'center', gap: 10 },
  badge: { minWidth: 28, height: 28, borderRadius: 14, backgroundColor: colors.onPrimary, alignItems: 'center', justifyContent: 'center', paddingHorizontal: 6 },
  badgeText: { fontFamily: fonts.bold, fontSize: 14, color: colors.primary },
  label: { fontFamily: fonts.bold, fontSize: 16, color: colors.onPrimary },
});
