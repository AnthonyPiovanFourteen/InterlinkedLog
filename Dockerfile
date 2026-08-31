# Base fixada por digest: a tag é móvel. Atualizar o digest é ato deliberado.
FROM oven/bun:1-alpine@sha256:07235578f79ef8c6f97d94aee7938e76f5cdba5f21ae5dbfdd3d3d38058437eb AS builder

WORKDIR /app

COPY package.json bun.lock ./

RUN bun install --frozen-lockfile

COPY . .

RUN bun run build

FROM oven/bun:1-alpine@sha256:07235578f79ef8c6f97d94aee7938e76f5cdba5f21ae5dbfdd3d3d38058437eb AS runtime

WORKDIR /app

COPY package.json bun.lock ./

RUN bun install --frozen-lockfile --production

COPY --from=builder /app/dist ./dist
COPY server.mjs ./

EXPOSE 3000

CMD ["bun", "server.mjs"]