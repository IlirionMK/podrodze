FROM node:25-slim

WORKDIR /usr/src/frontend

COPY package*.json ./
RUN npm install

COPY . .

CMD ["npm", "run", "dev", "--", "--host"]
