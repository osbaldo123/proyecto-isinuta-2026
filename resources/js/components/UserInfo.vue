<script setup lang="ts">
import { Avatar, AvatarImage } from '@/components/ui/avatar';
import type { User } from '@/types';
import { computed } from 'vue';

const DEFAULT_AVATAR = '/images/avatar-default.png';

interface Props {
    user: User;
    showSubtitle?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    showSubtitle: true,
});

const avatarSrc = computed(() =>
    props.user.avatar && props.user.avatar !== '' ? props.user.avatar : DEFAULT_AVATAR,
);

const subtitle = computed(() => props.user.role_label || props.user.email || '');
</script>

<template>
    <Avatar class="h-8 w-8 overflow-hidden rounded-lg">
        <AvatarImage :src="avatarSrc" :alt="user.name" class="size-full object-cover" />
    </Avatar>

    <div class="grid flex-1 text-left text-sm leading-tight">
        <span class="truncate font-medium">{{ user.name }}</span>
        <span v-if="showSubtitle && subtitle" class="truncate text-xs text-slate-400">{{ subtitle }}</span>
    </div>
</template>
