<template>
  <div>
    <q-btn
      dense
      flat
      :to="to"
      :color="color"
      class="desktop-only my_radio_high transition duration-500 ease-in-out transform hover:scale-105"
      @click="$emit('click')"
    >
      <div class="row">
        <q-knob
          show-value
          :indeterminate="indeterminate"
          :model-value="name != null ? name : 67"
          :size="size ? size : '29px'"
          class="shadow-9"
          style="border-radius: 5rem !important"
          :thickness="thickness ? thickness : 0.2"
          :color="color"
          track-color="grey-4"
        >
          <q-icon :name="icon ? icon : 'home'" size="xs" />
        </q-knob>
        <div class="text-weight-bolder self-center q-ml-sm" :class="label_class">
          <slot></slot>
        </div>
      </div>
    </q-btn>

    <q-btn
      dense
      flat
      :to="to"
      :color="color"
      class="mobile-only my_radio_high transition duration-500 ease-in-out transform hover:scale-105"
      @click="$emit('click')"
    >
      <div class="row">
        <q-icon :name="icon ? icon : 'home'" size="xs" />
      </div>
    </q-btn>
  </div>
</template>

<script setup>
defineProps(['name', 'label_class', 'indeterminate', 'icon', 'to', 'size', 'thickness', 'color'])
defineEmits(['click'])
</script>
