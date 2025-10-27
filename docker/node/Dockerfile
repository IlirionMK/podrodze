FROM node:25-alpine

WORKDIR /usr/src/frontend

COPY frontend/package*.json ./

RUN npm ci || npm install

COPY frontend .

CMD ["npm", "run", "dev", "--", "--host"]