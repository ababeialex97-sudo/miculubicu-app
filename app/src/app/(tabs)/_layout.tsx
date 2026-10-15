import { Tabs } from 'expo-router';

import type { ColorValue } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { Icon, type IconName } from '@/components/icon';
import { colors, fonts } from '@/constants/theme';

function tabIcon(name: IconName) {
  return function TabIcon({ color }: { color: ColorValue }) {
    return <Icon name={name} color={color} />;
  };
}

// The default bar (49pt) leaves only ~10px under the icon, which clips 12px labels.
const TAB_BAR_CONTENT_HEIGHT = 58;

export default function TabsLayout() {
  const insets = useSafeAreaInsets();
  return (
    <Tabs
      screenOptions={{
        headerShown: false,
        sceneStyle: { backgroundColor: colors.background },
        tabBarActiveTintColor: colors.accent,
        tabBarInactiveTintColor: colors.textTertiary,
        tabBarLabelStyle: { fontFamily: fonts.semibold, fontSize: 12, lineHeight: 16 },
        tabBarStyle: { height: TAB_BAR_CONTENT_HEIGHT + insets.bottom, backgroundColor: colors.bar, borderTopColor: colors.barBorder },
      }}>
      <Tabs.Screen name="index" options={{ title: 'Meniu', tabBarIcon: tabIcon('flame') }} />
      <Tabs.Screen name="comenzi" options={{ title: 'Comenzi', tabBarIcon: tabIcon('receipt') }} />
      <Tabs.Screen name="fidelitate" options={{ title: 'Fidelitate', tabBarIcon: tabIcon('gift') }} />
      <Tabs.Screen name="cont" options={{ title: 'Cont', tabBarIcon: tabIcon('user') }} />
    </Tabs>
  );
}
