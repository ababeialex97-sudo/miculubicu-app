import { StyleSheet, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

import { Icon } from '@/components/icon';
import { AppText } from '@/components/ui';
import { colors, fonts } from '@/constants/theme';

// Loyalty and promotions are built in step 5; this screen only explains the program for now.
export default function LoyaltyScreen() {
  return (
    <SafeAreaView edges={['top']} style={styles.screen}>
      <View style={styles.content}>
        <AppText variant="title" style={{ fontSize: 30 }}>
          Fidelitate
        </AppText>
        <View style={styles.card}>
          <AppText style={styles.cardTitle}>Cardul lui Bicu</AppText>
          <View style={styles.stamps}>
            {[0, 1, 2, 3].map((i) => (
              <View key={i} style={styles.stamp} />
            ))}
            <View style={[styles.stamp, styles.reward]}>
              <Icon name="gift" size={24} color={colors.onPrimary} />
            </View>
          </View>
          <AppText style={styles.cardText}>După 4 comenzi finalizate primești o reducere la următoarea comandă.</AppText>
        </View>
        <AppText variant="muted">Ștampilele și cupoanele apar aici în curând.</AppText>
      </View>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: colors.background },
  content: { padding: 20, gap: 18 },
  card: { padding: 20, borderRadius: 20, backgroundColor: colors.accent, gap: 16 },
  cardTitle: { fontFamily: fonts.heading, fontSize: 22, color: colors.onAccent },
  stamps: { flexDirection: 'row', gap: 10 },
  stamp: { flex: 1, aspectRatio: 1, borderRadius: 14, borderWidth: 2, borderStyle: 'dashed', borderColor: colors.onAccent },
  reward: { borderWidth: 0, backgroundColor: colors.primary, alignItems: 'center', justifyContent: 'center' },
  cardText: { fontFamily: fonts.semibold, fontSize: 15, lineHeight: 21, color: colors.onAccent },
});
