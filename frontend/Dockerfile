FROM node:22-alpine AS dev

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci

COPY . .

EXPOSE 5173

CMD ["npm", "run", "dev", "--", "--host", "0.0.0.0", "--port", "5173"]

FROM node:22-alpine AS build

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci

COPY . .

ARG VITE_API_URL=/api
ARG VITE_STORAGE_BASE_URL=
ARG VITE_GIS_BASE_URL=
ARG VITE_REVERB_APP_KEY=
ARG VITE_REVERB_URL=
ENV VITE_API_URL=${VITE_API_URL}
ENV VITE_STORAGE_BASE_URL=${VITE_STORAGE_BASE_URL}
ENV VITE_GIS_BASE_URL=${VITE_GIS_BASE_URL}
ENV VITE_REVERB_APP_KEY=${VITE_REVERB_APP_KEY}
ENV VITE_REVERB_URL=${VITE_REVERB_URL}

RUN test -n "$VITE_REVERB_APP_KEY" \
    && test -n "$VITE_REVERB_URL" \
    && rm -rf dist \
    && npm run build \
    && ENTRY="$(sed -n 's#.*src="\(/assets/index-[^"]*\.js\)".*#\1#p' dist/index.html)" \
    && test -n "$ENTRY" \
    && grep -Fq "$VITE_REVERB_APP_KEY" "dist$ENTRY" \
    && grep -Fq "$VITE_REVERB_URL" "dist$ENTRY"

FROM nginx:1.27-alpine AS production

RUN rm -rf /usr/share/nginx/html/*
COPY nginx.conf /etc/nginx/conf.d/default.conf
COPY --from=build /app/dist /usr/share/nginx/html

EXPOSE 80

CMD ["nginx", "-g", "daemon off;"]
