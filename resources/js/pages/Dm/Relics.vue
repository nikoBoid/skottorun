<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { useEcho } from '@laravel/echo-vue';
import {
    Archive,
    CheckCircle2,
    Gem,
    Image as ImageIcon,
    LockKeyhole,
    Pencil,
    Plus,
    Send,
    Trash2,
    UnlockKeyhole,
    X,
} from '@lucide/vue';
import { computed, ref } from 'vue';

type Player = { id: number; name: string };
type Revelation = {
    id: number;
    kind: 'skill' | 'lore';
    title: string;
    content: string;
    is_unlocked: boolean;
};
type Relic = {
    id: number;
    character_id: number | null;
    name: string;
    description: string | null;
    image_url: string | null;
    character?: Player | null;
    revelations: Revelation[];
};

const props = defineProps<{
    campaign: { id: number; name: string };
    players: Player[];
    relics: Relic[];
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Reliquie', href: '/dm/relics' }] },
});

const editingId = ref<number | null>(null);
const showForm = ref(false);
const form = useForm({
    character_id: null as number | null,
    name: '',
    description: '',
    image: null as File | null,
    remove_image: false,
    revelations: [] as Array<{
        id: number | null;
        kind: 'skill' | 'lore';
        title: string;
        content: string;
    }>,
});

const archivedCount = computed(
    () => props.relics.filter((relic) => !relic.character_id).length,
);

useEcho(`campaign.${props.campaign.id}`, 'CampaignChanged', () => {
    router.reload({ only: ['relics', 'players'] });
});

function newRelic() {
    editingId.value = null;
    form.reset();
    form.revelations = [];
    form.clearErrors();
    showForm.value = true;
}

function editRelic(relic: Relic) {
    editingId.value = relic.id;
    form.clearErrors();
    form.character_id = relic.character_id;
    form.name = relic.name;
    form.description = relic.description ?? '';
    form.image = null;
    form.remove_image = false;
    form.revelations = relic.revelations.map((revelation) => ({
        id: revelation.id,
        kind: revelation.kind,
        title: revelation.title,
        content: revelation.content,
    }));
    showForm.value = true;
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function closeForm() {
    showForm.value = false;
    editingId.value = null;
    form.reset();
    form.revelations = [];
    form.clearErrors();
}

function addRevelation(kind: 'skill' | 'lore') {
    form.revelations.push({ id: null, kind, title: '', content: '' });
}

function submit() {
    const target = editingId.value
        ? `/dm/relics/${editingId.value}`
        : '/dm/relics';

    form.transform((data) =>
        editingId.value ? { ...data, _method: 'patch' } : data,
    ).post(target, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: closeForm,
    });
}

function assign(relic: Relic, value: string) {
    router.patch(
        `/dm/relics/${relic.id}`,
        { character_id: value ? Number(value) : null },
        { preserveScroll: true },
    );
}

function toggleRevelation(revelation: Revelation) {
    router.patch(
        `/dm/relic-revelations/${revelation.id}`,
        { is_unlocked: !revelation.is_unlocked },
        { preserveScroll: true },
    );
}

function destroyRelic(relic: Relic) {
    if (window.confirm(`Eliminare definitivamente «${relic.name}»?`)) {
        router.delete(`/dm/relics/${relic.id}`, { preserveScroll: true });
    }
}

function setImage(event: Event) {
    form.image = (event.target as HTMLInputElement).files?.item(0) ?? null;
    form.remove_image = false;
}
</script>

<template>
    <Head title="Archivio reliquie" />

    <div
        class="min-h-full bg-[#f6f2e8] px-4 py-8 text-stone-900 sm:px-8 dark:bg-[#111814] dark:text-stone-100"
    >
        <div class="mx-auto max-w-7xl space-y-6">
            <header
                class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between"
            >
                <div>
                    <p
                        class="text-xs font-bold tracking-[0.2em] text-amber-700 uppercase dark:text-amber-300"
                    >
                        Forgia del Dungeon Master
                    </p>
                    <h1 class="mt-1 font-serif text-3xl font-bold">
                        Archivio reliquie
                    </h1>
                    <p class="mt-2 text-sm text-stone-500">
                        {{ relics.length }} reliquie ·
                        {{ archivedCount }} ancora custodite nell’archivio.
                    </p>
                </div>
                <button class="primary-button" type="button" @click="newRelic">
                    <Plus class="size-4" /> Crea reliquia
                </button>
            </header>

            <form
                v-if="showForm"
                class="panel space-y-5"
                @submit.prevent="submit"
            >
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="font-serif text-2xl font-bold">
                            {{
                                editingId
                                    ? 'Modifica reliquia'
                                    : 'Prepara una reliquia'
                            }}
                        </h2>
                        <p class="mt-1 text-sm text-stone-500">
                            Puoi conservarla oppure consegnarla subito a un
                            personaggio.
                        </p>
                    </div>
                    <button
                        class="icon-button"
                        type="button"
                        aria-label="Chiudi"
                        @click="closeForm"
                    >
                        <X class="size-4" />
                    </button>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <label class="field"
                        ><span>Nome</span><input v-model="form.name" required
                    /></label>
                    <label class="field">
                        <span>Destinazione</span>
                        <select v-model="form.character_id">
                            <option :value="null">Archivio del DM</option>
                            <option
                                v-for="player in players"
                                :key="player.id"
                                :value="player.id"
                            >
                                Affida a {{ player.name }}
                            </option>
                        </select>
                    </label>
                    <label class="field md:col-span-2"
                        ><span>Descrizione base</span
                        ><textarea
                            v-model="form.description"
                            rows="4"
                        ></textarea>
                    </label>
                    <label class="upload-card">
                        <ImageIcon class="size-5" />
                        <span class="min-w-0"
                            ><strong class="block text-sm">Immagine</strong
                            ><small class="block truncate text-stone-500">{{
                                form.image?.name ||
                                'JPG, PNG o WebP · massimo 5 MB'
                            }}</small></span
                        >
                        <input
                            class="sr-only"
                            type="file"
                            accept="image/jpeg,image/png,image/webp"
                            @change="setImage"
                        />
                    </label>
                    <label
                        v-if="editingId"
                        class="flex items-center gap-3 rounded-xl border border-stone-200 px-4 py-3 text-sm font-semibold dark:border-white/10"
                    >
                        <input
                            v-model="form.remove_image"
                            type="checkbox"
                            class="size-4 accent-red-700"
                            :disabled="Boolean(form.image)"
                        />
                        Rimuovi l’immagine attuale
                    </label>
                </div>

                <div
                    class="rounded-2xl border border-stone-200 bg-stone-50 p-4 dark:border-white/10 dark:bg-black/20"
                >
                    <div
                        class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div>
                            <h3 class="font-bold">
                                Abilità e descrizioni da sbloccare
                            </h3>
                            <p class="text-xs text-stone-500">
                                I nuovi contenuti partono sempre sigillati.
                            </p>
                        </div>
                        <div class="flex gap-2">
                            <button
                                class="secondary-button"
                                type="button"
                                @click="addRevelation('skill')"
                            >
                                <Plus class="size-3.5" /> Abilità
                            </button>
                            <button
                                class="secondary-button"
                                type="button"
                                @click="addRevelation('lore')"
                            >
                                <Plus class="size-3.5" /> Descrizione
                            </button>
                        </div>
                    </div>

                    <div class="space-y-3">
                        <div
                            v-for="(revelation, index) in form.revelations"
                            :key="revelation.id ?? `new-${index}`"
                            class="grid gap-3 rounded-xl border border-stone-200 bg-white p-3 md:grid-cols-[130px_1fr_auto] dark:border-white/10 dark:bg-white/5"
                        >
                            <label class="field"
                                ><span>Tipo</span
                                ><select v-model="revelation.kind">
                                    <option value="skill">Abilità</option>
                                    <option value="lore">Descrizione</option>
                                </select></label
                            >
                            <label class="field"
                                ><span>Titolo</span
                                ><input v-model="revelation.title" required
                            /></label>
                            <button
                                class="danger-button self-end"
                                type="button"
                                aria-label="Rimuovi rivelazione"
                                @click="form.revelations.splice(index, 1)"
                            >
                                <Trash2 class="size-4" />
                            </button>
                            <label class="field md:col-span-3"
                                ><span>Contenuto completo</span
                                ><textarea
                                    v-model="revelation.content"
                                    rows="3"
                                    required
                                ></textarea>
                            </label>
                        </div>
                        <p
                            v-if="!form.revelations.length"
                            class="py-4 text-center text-xs text-stone-500"
                        >
                            Nessun segreto: la reliquia sarà completamente
                            rivelata.
                        </p>
                    </div>
                </div>

                <p
                    v-if="Object.keys(form.errors).length"
                    class="text-sm font-semibold text-red-700 dark:text-red-300"
                >
                    {{ Object.values(form.errors)[0] }}
                </p>
                <div class="flex gap-3">
                    <button class="primary-button" :disabled="form.processing">
                        <CheckCircle2 class="size-4" />
                        {{ editingId ? 'Salva modifiche' : 'Crea reliquia' }}
                    </button>
                    <button
                        class="secondary-button"
                        type="button"
                        @click="closeForm"
                    >
                        Annulla
                    </button>
                </div>
            </form>

            <section class="grid gap-5 lg:grid-cols-2">
                <article
                    v-for="relic in relics"
                    :key="relic.id"
                    class="relic-card"
                >
                    <div
                        v-if="relic.image_url"
                        class="aspect-[16/7] overflow-hidden bg-stone-900"
                    >
                        <img
                            :src="relic.image_url"
                            :alt="relic.name"
                            class="size-full object-cover"
                        />
                    </div>
                    <div
                        v-else
                        class="grid aspect-[16/4] place-items-center bg-[radial-gradient(circle_at_center,#fde68a_0,transparent_65%)] text-amber-900 dark:text-amber-200"
                    >
                        <Gem class="size-9" />
                    </div>
                    <div class="p-5">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <span
                                    class="mb-2 inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-[11px] font-bold"
                                    :class="
                                        relic.character_id
                                            ? 'bg-emerald-100 text-emerald-900 dark:bg-emerald-950 dark:text-emerald-200'
                                            : 'bg-violet-100 text-violet-900 dark:bg-violet-950 dark:text-violet-200'
                                    "
                                >
                                    <Send
                                        v-if="relic.character_id"
                                        class="size-3"
                                    /><Archive v-else class="size-3" />
                                    {{ relic.character?.name || 'Archivio DM' }}
                                </span>
                                <h2 class="font-serif text-xl font-bold">
                                    {{ relic.name }}
                                </h2>
                            </div>
                            <div class="flex gap-1">
                                <button
                                    class="icon-button"
                                    type="button"
                                    aria-label="Modifica"
                                    @click="editRelic(relic)"
                                >
                                    <Pencil class="size-4" />
                                </button>
                                <button
                                    class="danger-button"
                                    type="button"
                                    aria-label="Elimina"
                                    @click="destroyRelic(relic)"
                                >
                                    <Trash2 class="size-4" />
                                </button>
                            </div>
                        </div>

                        <p
                            class="mt-3 text-sm leading-6 text-stone-600 dark:text-stone-300"
                        >
                            {{
                                relic.description ||
                                'Nessuna descrizione annotata.'
                            }}
                        </p>
                        <label class="field mt-4"
                            ><span>Consegna</span
                            ><select
                                :value="relic.character_id ?? ''"
                                @change="
                                    assign(
                                        relic,
                                        ($event.target as HTMLSelectElement)
                                            .value,
                                    )
                                "
                            >
                                <option value="">
                                    Conserva nell’archivio DM
                                </option>
                                <option
                                    v-for="player in players"
                                    :key="player.id"
                                    :value="player.id"
                                >
                                    Affida a {{ player.name }}
                                </option>
                            </select></label
                        >

                        <div
                            v-if="relic.revelations.length"
                            class="mt-5 space-y-3"
                        >
                            <p
                                class="text-xs font-bold tracking-wider text-stone-400 uppercase"
                            >
                                Rivelazioni
                            </p>
                            <div
                                v-for="revelation in relic.revelations"
                                :key="revelation.id"
                                class="rounded-xl border p-3"
                                :class="
                                    revelation.is_unlocked
                                        ? 'border-emerald-300/50 bg-emerald-50 dark:bg-emerald-950/25'
                                        : 'border-violet-300/40 bg-violet-50 dark:bg-violet-950/25'
                                "
                            >
                                <div
                                    class="flex items-start justify-between gap-3"
                                >
                                    <div>
                                        <span
                                            class="flex items-center gap-1 text-[10px] font-bold tracking-wider uppercase"
                                            ><UnlockKeyhole
                                                v-if="revelation.is_unlocked"
                                                class="size-3"
                                            /><LockKeyhole
                                                v-else
                                                class="size-3"
                                            />{{
                                                revelation.kind === 'skill'
                                                    ? 'Abilità'
                                                    : 'Descrizione'
                                            }}
                                            ·
                                            {{
                                                revelation.is_unlocked
                                                    ? 'Sbloccata'
                                                    : 'Sigillata'
                                            }}</span
                                        >
                                        <h3 class="mt-1 text-sm font-bold">
                                            {{ revelation.title }}
                                        </h3>
                                    </div>
                                    <button
                                        class="unlock-button"
                                        type="button"
                                        :class="
                                            revelation.is_unlocked
                                                ? 'bg-stone-200 text-stone-700 dark:bg-white/10 dark:text-stone-200'
                                                : 'bg-violet-800 text-white'
                                        "
                                        @click="toggleRevelation(revelation)"
                                    >
                                        {{
                                            revelation.is_unlocked
                                                ? 'Sigilla'
                                                : 'Sblocca'
                                        }}
                                    </button>
                                </div>
                                <p
                                    class="mt-2 text-sm leading-6 whitespace-pre-wrap text-stone-600 dark:text-stone-300"
                                >
                                    {{ revelation.content }}
                                </p>
                            </div>
                        </div>
                    </div>
                </article>
            </section>

            <div v-if="!relics.length" class="panel empty-state">
                <Gem class="size-9" />
                <p>Nessuna reliquia preparata.</p>
                <span>La forgia attende la prima leggenda.</span>
            </div>
        </div>
    </div>
</template>

<style scoped>
@reference '../../../css/app.css';
.panel {
    @apply rounded-2xl border border-stone-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-white/5;
}
.relic-card {
    @apply overflow-hidden rounded-2xl border border-amber-700/20 bg-gradient-to-br from-white to-amber-50 shadow-sm dark:from-white/10 dark:to-amber-950/20;
}
.field {
    @apply flex min-w-0 flex-col gap-1.5 text-xs font-semibold text-stone-600 dark:text-stone-300;
}
.field input,
.field textarea,
.field select {
    @apply w-full rounded-xl border border-stone-200 bg-white px-3 py-2.5 text-sm font-normal text-stone-900 outline-none focus:border-emerald-700 focus:ring-2 focus:ring-emerald-700/10 dark:border-white/10 dark:bg-black/20 dark:text-stone-100;
}
.primary-button {
    @apply inline-flex items-center justify-center gap-2 rounded-xl bg-emerald-800 px-4 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-emerald-700 disabled:opacity-50;
}
.secondary-button {
    @apply inline-flex items-center gap-1.5 rounded-xl border border-stone-200 bg-white px-3 py-2.5 text-xs font-bold text-stone-700 transition hover:border-emerald-700 hover:text-emerald-800 dark:border-white/10 dark:bg-white/5 dark:text-stone-200;
}
.icon-button,
.danger-button {
    @apply grid size-9 shrink-0 place-items-center rounded-lg text-stone-400 transition hover:bg-stone-100 hover:text-stone-700 dark:hover:bg-white/10 dark:hover:text-white;
}
.danger-button {
    @apply hover:bg-red-50 hover:text-red-700 dark:hover:bg-red-950/30 dark:hover:text-red-300;
}
.unlock-button {
    @apply shrink-0 rounded-lg px-2.5 py-1.5 text-xs font-bold transition;
}
.upload-card {
    @apply flex cursor-pointer items-center gap-3 rounded-xl border border-dashed border-stone-300 bg-stone-50 px-4 py-3 transition hover:border-emerald-700 dark:border-white/15 dark:bg-black/20;
}
.empty-state {
    @apply flex flex-col items-center py-12 text-center text-stone-400;
}
.empty-state p {
    @apply mt-3 font-semibold text-stone-700 dark:text-stone-200;
}
.empty-state span {
    @apply mt-1 text-xs;
}
</style>
