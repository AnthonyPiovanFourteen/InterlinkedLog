FROM oven/bun:1-alpine AS builder

WORKDIR /app

COPY package.json bun.lock ./

RUN bun install --frozen-lockfile

COPY . .

RUN bun run build

FROM oven/bun:1-alpine AS runtime

WORKDIR /app

COPY package.json bun.lock ./

RUN bun install --frozen-lockfile --production

COPY --from=builder /app/dist ./dist
COPY server.mjs ./

EXPOSE 3000

CMD ["bun", "server.mjs"]