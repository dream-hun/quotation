import { Scale } from 'lucide-react';
import type { SVGAttributes } from 'react';

/**
 * The stroke-based mark matches the landing page; `fill: none` overrides the
 * `fill-current` utility that call sites apply for the previous filled logo.
 */
export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return <Scale {...props} style={{ ...props.style, fill: 'none' }} />;
}
