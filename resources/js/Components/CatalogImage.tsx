import type { ImgHTMLAttributes } from 'react';

type Props = Omit<ImgHTMLAttributes<HTMLImageElement>, 'src' | 'width' | 'height'> & {
    src: string;
    width?: number | null;
    height?: number | null;
};

export default function CatalogImage({ src, width, height, sizes = '100vw', ...props }: Props) {
    const optimized = src.startsWith('/catalog/') && src.endsWith('.webp');
    const stem = optimized ? src.slice(0, -5) : null;
    const fullWidth = width && width > 960 ? width : 1122;

    return <img
        {...props}
        src={src}
        srcSet={stem ? `${stem}-640.webp 640w, ${stem}-960.webp 960w, ${src} ${fullWidth}w` : undefined}
        sizes={stem ? sizes : undefined}
        width={width ?? undefined}
        height={height ?? undefined}
        decoding={props.decoding ?? 'async'}
    />;
}
