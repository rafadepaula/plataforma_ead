/**
 * WatchTracker - tempo EFETIVAMENTE assistido, por segundo único.
 *
 * Substitui a leitura do playhead (`getCurrentTime()`) que o polling antigo
 * reportava como `watched_seconds` — número inflado por qualquer seek para
 * frente (pular para os 8min de um vídeo de 10min "contabilizava" 8min).
 *
 * O `PlayerController` alimenta este tracker a cada `timeupdate` e a cada
 * `statechange`; só segundos reproduzidos no estado PLAYING entram na conta
 * — pausa, buffering e seek interrompem a amostragem, e reassistir um trecho
 * é idempotente porque a dedupe é o próprio `Set` de segundos.
 *
 * O transporte é em intervalos `[start, end)` de segundos contíguos
 * (compacto no fio), e o servidor — não este módulo — é a autoridade: ele
 * une os intervalos aos já persistidos via `VideoWatchCalculator` e deriva
 * o percentual de segundos únicos.
 */
export class WatchTracker {
    constructor() {
        /** Segundos ainda não confirmados pelo POST de progresso. */
        this.pendingSeconds = new Set();
        /** Último estado anunciado pelo adapter ('playing' | ...). */
        this.state = 'unstarted';
        /**
         * Playhead mais recente, em QUALQUER estado (timeupdate dispara em
         * seek também): é o bookmark exato de "retomar de onde parou" — o
         * aluno que busca 0:20 → 0:50 e recarrega a página volta aos 0:50,
         * mesmo sem ter assistido o segundo 50.
         */
        this.lastPosition = null;
    }

    onStateChange(state) {
        this.state = state;
    }

    onTimeUpdate(currentTime, duration) {
        const second = Math.floor(currentTime);

        if (Number.isFinite(second) && second >= 0) {
            this.lastPosition = second;
        }

        if (this.state !== 'playing') return;

        if (!Number.isFinite(second) || second < 0) return;
        if (Number.isFinite(duration) && duration > 0 && second >= Math.floor(duration)) return;

        this.pendingSeconds.add(second);
    }

    /**
     * Amostra o segundo corrente. Só conta em PLAYING: nos demais estados o
     * playhead se move sem reprodução (seek) ou não se move (pausa/buffer).
     * O último segundo (`second >= duration`) nunca entra — um vídeo de 540s
     * assistido por completo cobre os segundos 0..539, exatamente 540s únicos.
     */
    onTimeUpdate(currentTime, duration) {
        if (this.state !== 'playing') return;

        const second = Math.floor(currentTime);
        if (!Number.isFinite(second) || second < 0) return;
        if (Number.isFinite(duration) && duration > 0 && second >= Math.floor(duration)) return;

        this.pendingSeconds.add(second);
    }

    /**
     * Intervalos `[start, end)` dos segundos pendentes, e limpa o pendente.
     * Devolve `[]` quando nada novo foi assistido desde o último POST
     * (ex.: player pausado) — o poll então nem dispara requisição.
     */
    takePendingRanges() {
        const ranges = this.toRanges(this.pendingSeconds);
        this.pendingSeconds.clear();

        return ranges;
    }

    /**
     * Devolve intervalos ao pendente quando o POST falhou — o progresso só
     * é considerado enviado depois de confirmação do servidor.
     */
    restore(ranges) {
        ranges.forEach(([start, end]) => {
            for (let second = start; second < end; second += 1) {
                this.pendingSeconds.add(second);
            }
        });
    }

    /**
     * Segundos avulsos em intervalos contíguos: `{0,1,2,7,8}` vira
     * `[[0,3],[7,9]]` — o fio compacto que `UpdateLessonProgressRequest`
     * espera.
     */
    toRanges(seconds) {
        const sorted = [...seconds].sort((a, b) => a - b);
        const ranges = [];
        let start = null;
        let previous = null;

        sorted.forEach((second) => {
            if (start !== null && second === previous + 1) {
                previous = second;
                return;
            }

            if (start !== null) {
                ranges.push([start, previous + 1]);
            }

            start = second;
            previous = second;
        });

        if (start !== null) {
            ranges.push([start, previous + 1]);
        }

        return ranges;
    }
}

export default WatchTracker;
