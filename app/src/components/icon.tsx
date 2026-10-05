import type { ColorValue } from 'react-native';
import Svg, { Circle, Path, Rect } from 'react-native-svg';

// Stroke icons drawn in the mockup, so the app matches it without an icon font.
export type IconName = 'back' | 'plus' | 'minus' | 'pin' | 'chevronDown' | 'flame' | 'receipt' | 'gift' | 'user' | 'phone' | 'check' | 'truck';

type Props = {
  name: IconName;
  size?: number;
  color: ColorValue;
  strokeWidth?: number;
};

export function Icon({ name, size = 22, color, strokeWidth = 2 }: Props) {
  const common = {
    fill: 'none',
    stroke: color,
    strokeWidth,
    strokeLinecap: 'round' as const,
    strokeLinejoin: 'round' as const,
  };

  return (
    <Svg width={size} height={size} viewBox="0 0 24 24">
      {name === 'back' && <Path d="M15 6l-6 6 6 6" {...common} />}
      {name === 'plus' && <Path d="M12 5v14M5 12h14" {...common} />}
      {name === 'minus' && <Path d="M5 12h14" {...common} />}
      {name === 'pin' && (
        <>
          <Path d="M12 21s-7-6.5-7-11a7 7 0 0 1 14 0c0 4.5-7 11-7 11z" {...common} />
          <Circle cx="12" cy="10" r="2.5" {...common} />
        </>
      )}
      {name === 'chevronDown' && <Path d="M6 9l6 6 6-6" {...common} />}
      {name === 'flame' && <Path d="M12 3c1 3 4 4.5 4 8.5a4 4 0 0 1-8 0c0-2 1-3 2-4 0 1.5.8 2.5 2 2.5 0-2.5-1.5-4.5 0-7z" {...common} />}
      {name === 'receipt' && (
        <>
          <Path d="M6 3h12v18l-3-2-3 2-3-2-3 2z" {...common} />
          <Path d="M9 8h6M9 12h6" {...common} />
        </>
      )}
      {name === 'gift' && (
        <>
          <Rect x="3" y="8" width="18" height="5" rx="1" {...common} />
          <Path d="M5 13v8h14v-8M12 8v13M12 8c-1.5-3-5-3-5-1s3 1 5 1zM12 8c1.5-3 5-3 5-1s-3 1-5 1z" {...common} />
        </>
      )}
      {name === 'user' && (
        <>
          <Circle cx="12" cy="8" r="4" {...common} />
          <Path d="M4 21c1.5-4 4.5-6 8-6s6.5 2 8 6" {...common} />
        </>
      )}
      {name === 'phone' && <Path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2z" {...common} />}
      {name === 'check' && <Path d="M5 12l5 5L20 7" {...common} />}
      {name === 'truck' && (
        <>
          <Path d="M3 7h11v9H3z" {...common} />
          <Path d="M14 10h4l3 3v3h-7" {...common} />
          <Circle cx="7" cy="18" r="1.8" {...common} />
          <Circle cx="17" cy="18" r="1.8" {...common} />
        </>
      )}
    </Svg>
  );
}
