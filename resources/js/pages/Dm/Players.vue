<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import {
    CheckCircle2,
    Pencil,
    ShieldOff,
    UserPlus,
    Users,
    X,
} from '@lucide/vue';
import { computed, ref } from 'vue';

type Player = {
    id: number;
    name: string;
    ancestry: string | null;
    class_name: string | null;
    level: number;
    user: {
        id: number;
        name: string;
        username: string;
        is_active: boolean;
    };
};

defineProps<{
    campaign: { id: number; name: string };
    players: Player[];
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Personaggi', href: '/dm/players' }] },
});

const editingId = ref<number | null>(null);
const showForm = ref(false);
const form = useForm({
    name: '',
    username: '',
    password: '',
    character_name: '',
    ancestry: '',
    class_name: '',
    level: 1,
    is_active: true,
});

const formTitle = computed(() =>
    editingId.value ? 'Modifica avventuriero' : 'Nuovo avventuriero',
);

function createPlayer() {
    editingId.value = null;
    form.reset();
    form.clearErrors();
    showForm.value = true;
}

function editPlayer(player: Player) {
    editingId.value = player.id;
    form.clearErrors();
    form.name = player.user.name;
    form.username = player.user.username;
    form.password = '';
    form.character_name = player.name;
    form.ancestry = player.ancestry ?? '';
    form.class_name = player.class_name ?? '';
    form.level = player.level;
    form.is_active = player.user.is_active;
    showForm.value = true;
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function closeForm() {
    showForm.value = false;
    editingId.value = null;
    form.reset();
    form.clearErrors();
}

function submit() {
    const options = {
        preserveScroll: true,
        onSuccess: closeForm,
    };

    if (editingId.value) {
        form.patch(`/dm/players/${editingId.value}`, options);

        return;
    }

    form.post('/dm/players', options);
}
</script>

<template>
    <Head title="Gestione personaggi" />

    <div
        class="min-h-full bg-[#f6f2e8] px-4 py-8 text-stone-900 sm:px-8 dark:bg-[#111814] dark:text-stone-100"
    >
        <div class="mx-auto max-w-6xl space-y-6">
            <header
                class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between"
            >
                <div>
                    <p
                        class="text-xs font-bold tracking-[0.2em] text-emerald-700 uppercase dark:text-emerald-300"
                    >
                        Schermo del Dungeon Master
                    </p>
                    <h1 class="mt-1 font-serif text-3xl font-bold">
                        Personaggi
                    </h1>
                    <p class="mt-2 text-sm text-stone-500">
                        Gestisci account e schede dei giocatori di
                        {{ campaign.name }}.
                    </p>
                </div>
                <button
                    class="primary-button"
                    type="button"
                    @click="createPlayer"
                >
                    <UserPlus class="size-4" /> Nuovo player
                </button>
            </header>

            <form v-if="showForm" class="panel" @submit.prevent="submit">
                <div class="mb-5 flex items-start justify-between gap-4">
                    <div>
                        <h2 class="font-serif text-2xl font-bold">
                            {{ formTitle }}
                        </h2>
                        <p class="mt-1 text-sm text-stone-500">
                            Username e password saranno consegnati direttamente
                            al giocatore.
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

                <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                    <label class="field"
                        ><span>Nome player</span
                        ><input v-model="form.name" required
                    /></label>
                    <label class="field"
                        ><span>Username</span
                        ><input
                            v-model="form.username"
                            minlength="3"
                            maxlength="32"
                            pattern="[A-Za-z0-9._-]+"
                            autocomplete="off"
                            required
                    /></label>
                    <label class="field">
                        <span>{{
                            editingId
                                ? 'Nuova password (facoltativa)'
                                : 'Password iniziale'
                        }}</span>
                        <input
                            v-model="form.password"
                            type="password"
                            minlength="8"
                            autocomplete="new-password"
                            :required="!editingId"
                        />
                    </label>
                    <label class="field"
                        ><span>Nome personaggio</span
                        ><input v-model="form.character_name" required
                    /></label>
                    <label class="field"
                        ><span>Razza</span><input v-model="form.ancestry"
                    /></label>
                    <label class="field"
                        ><span>Classe</span><input v-model="form.class_name"
                    /></label>
                    <label class="field"
                        ><span>Livello</span
                        ><input
                            v-model.number="form.level"
                            type="number"
                            min="1"
                            max="20"
                            required
                    /></label>
                    <label
                        class="flex items-center gap-3 rounded-xl border border-stone-200 bg-stone-50 px-4 py-3 text-sm font-semibold dark:border-white/10 dark:bg-black/20"
                    >
                        <input
                            v-model="form.is_active"
                            type="checkbox"
                            class="size-4 accent-emerald-700"
                        />
                        Account attivo
                    </label>
                </div>

                <p
                    v-if="Object.keys(form.errors).length"
                    class="mt-4 text-sm font-semibold text-red-700 dark:text-red-300"
                >
                    {{ Object.values(form.errors)[0] }}
                </p>
                <div class="mt-5 flex gap-3">
                    <button class="primary-button" :disabled="form.processing">
                        <CheckCircle2 class="size-4" />
                        {{ editingId ? 'Salva modifiche' : 'Crea personaggio' }}
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

            <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                <article
                    v-for="player in players"
                    :key="player.id"
                    class="panel flex flex-col"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex min-w-0 items-center gap-3">
                            <span
                                class="grid size-11 shrink-0 place-items-center rounded-xl bg-amber-200 font-serif text-lg font-bold text-emerald-950"
                            >
                                {{ player.name.slice(0, 1) }}
                            </span>
                            <div class="min-w-0">
                                <h2
                                    class="truncate font-serif text-xl font-bold"
                                >
                                    {{ player.name }}
                                </h2>
                                <p class="truncate text-xs text-stone-500">
                                    {{ player.user.name }} · @{{
                                        player.user.username
                                    }}
                                </p>
                            </div>
                        </div>
                        <span
                            class="rounded-full px-2.5 py-1 text-[11px] font-bold"
                            :class="
                                player.user.is_active
                                    ? 'bg-emerald-100 text-emerald-900 dark:bg-emerald-950 dark:text-emerald-200'
                                    : 'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-200'
                            "
                        >
                            {{
                                player.user.is_active ? 'Attivo' : 'Disattivato'
                            }}
                        </span>
                    </div>

                    <div class="mt-5 grid grid-cols-3 gap-2 text-center">
                        <div class="stat">
                            <strong>{{ player.ancestry || '—' }}</strong
                            ><span>Razza</span>
                        </div>
                        <div class="stat">
                            <strong>{{ player.class_name || '—' }}</strong
                            ><span>Classe</span>
                        </div>
                        <div class="stat">
                            <strong>{{ player.level }}</strong
                            ><span>Livello</span>
                        </div>
                    </div>

                    <div class="mt-auto pt-5">
                        <button
                            class="secondary-button w-full justify-center"
                            type="button"
                            @click="editPlayer(player)"
                        >
                            <Pencil class="size-4" /> Gestisci
                        </button>
                    </div>
                </article>
            </section>

            <div v-if="!players.length" class="panel empty-state">
                <Users class="size-9" />
                <p>Nessun personaggio presente.</p>
                <span>Crea il primo account per iniziare la compagnia.</span>
            </div>

            <div class="flex items-center gap-2 text-xs text-stone-500">
                <ShieldOff class="size-4" /> Gli account disattivati conservano
                tutto lo storico ma non possono accedere.
            </div>
        </div>
    </div>
</template>

<style scoped>
@reference '../../../css/app.css';
.panel {
    @apply rounded-2xl border border-stone-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-white/5;
}
.field {
    @apply flex min-w-0 flex-col gap-1.5 text-xs font-semibold text-stone-600 dark:text-stone-300;
}
.field input {
    @apply w-full rounded-xl border border-stone-200 bg-white px-3 py-2.5 text-sm font-normal text-stone-900 outline-none focus:border-emerald-700 focus:ring-2 focus:ring-emerald-700/10 dark:border-white/10 dark:bg-black/20 dark:text-stone-100;
}
.primary-button {
    @apply inline-flex items-center justify-center gap-2 rounded-xl bg-emerald-800 px-4 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-emerald-700 disabled:opacity-50;
}
.secondary-button {
    @apply inline-flex items-center gap-2 rounded-xl border border-stone-200 bg-white px-4 py-2.5 text-sm font-bold text-stone-700 transition hover:border-emerald-700 hover:text-emerald-800 dark:border-white/10 dark:bg-white/5 dark:text-stone-200;
}
.icon-button {
    @apply grid size-9 place-items-center rounded-lg text-stone-400 transition hover:bg-stone-100 hover:text-stone-700 dark:hover:bg-white/10 dark:hover:text-white;
}
.stat {
    @apply flex min-w-0 flex-col rounded-xl bg-stone-50 px-2 py-3 dark:bg-black/20;
}
.stat strong {
    @apply truncate text-sm;
}
.stat span {
    @apply mt-1 text-[10px] text-stone-500;
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
