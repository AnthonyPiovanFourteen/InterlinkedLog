declare module "react-simple-maps" {
  import type { ComponentType, ReactNode } from "react";

  interface GeographyProps {
    geography: any;
    fill?: string;
    stroke?: string;
    strokeWidth?: number;
    style?: {
      default?: Record<string, any>;
      hover?: Record<string, any>;
      pressed?: Record<string, any>;
    };
  }

  interface GeographiesProps {
    geography: string;
    children: (data: { geographies: any[] }) => ReactNode;
  }

  interface MarkerProps {
    coordinates: [number, number];
    children?: ReactNode;
  }

  interface LineProps {
    from: [number, number];
    to: [number, number];
    stroke?: string;
    strokeWidth?: number;
    strokeLinecap?: string;
  }

  interface ZoomableGroupProps {
    zoom?: number;
    center?: [number, number];
    children?: ReactNode;
  }

  export const ComposableMap: ComponentType<{
    projection?: string;
    projectionConfig?: Record<string, any>;
    style?: Record<string, any>;
    children?: ReactNode;
  }>;

  export const Geographies: ComponentType<GeographiesProps>;
  export const Geography: ComponentType<GeographyProps>;
  export const Marker: ComponentType<MarkerProps>;
  export const Line: ComponentType<LineProps>;
  export const ZoomableGroup: ComponentType<ZoomableGroupProps>;
}
