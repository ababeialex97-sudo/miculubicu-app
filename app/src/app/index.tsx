import { StyleSheet, Text, View } from 'react-native';

import { colors } from '@/constants/theme';

// Placeholder until the menu screen is built (step 3 in CLAUDE.md).
export default function Index() {
  return (
    <View style={styles.container}>
      <Text style={styles.title}>Micu&apos; lu&apos; Bicu</Text>
      <Text style={styles.subtitle}>Meniul vine în curând.</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: colors.background,
    gap: 8,
  },
  title: {
    color: colors.text,
    fontSize: 28,
    fontWeight: '700',
  },
  subtitle: {
    color: colors.textMuted,
    fontSize: 16,
  },
});
