import type { NextConfig } from 'next';

const nextConfig: NextConfig = {
    /**
     * Сборка в самодостаточный сервер: в образ попадают только реально
     * использованные модули, а не весь node_modules. Для контейнера это
     * разница на порядок в размере.
     */
    output: 'standalone',
};

export default nextConfig;
