# reg-race-result

Anmälan, tidtagning och resultat för Högby IF:s lopp. Se [issues](https://github.com/jimmitjoo/reg-race-result/issues) och användarguiden för tävlingsdagen (länkad från #2).

## Utveckling

Allt körs via Docker, ingen lokal PHP krävs.

```sh
docker run --rm -u $(id -u):$(id -g) -v $PWD:/app -w /app composer:latest ./vendor/bin/pest   # tester
docker run --rm -u $(id -u):$(id -g) -v $PWD:/app -w /app node:22 npm run build                # frontend
```
