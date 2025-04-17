<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

interface Props {
    className?: HTMLAttributes['class'];
}

defineProps<Props>();
</script>

<template>
    <!--<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 40 42" :class="className" v-bind="$attrs">
        <path
            fill="currentColor"
            fill-rule="evenodd"
            clip-rule="evenodd"
            d="M17.2 5.633 8.6.855 0 5.633v26.51l16.2 9 16.2-9v-8.442l7.6-4.223V9.856l-8.6-4.777-8.6 4.777V18.3l-5.6 3.111V5.633ZM38 18.301l-5.6 3.11v-6.157l5.6-3.11V18.3Zm-1.06-7.856-5.54 3.078-5.54-3.079 5.54-3.078 5.54 3.079ZM24.8 18.3v-6.157l5.6 3.111v6.158L24.8 18.3Zm-1 1.732 5.54 3.078-13.14 7.302-5.54-3.078 13.14-7.3v-.002Zm-16.2 7.89 7.6 4.222V38.3L2 30.966V7.92l5.6 3.111v16.892ZM8.6 9.3 3.06 6.222 8.6 3.143l5.54 3.08L8.6 9.3Zm21.8 15.51-13.2 7.334V38.3l13.2-7.334v-6.156ZM9.6 11.034l5.6-3.11v14.6l-5.6 3.11v-14.6Z"
        />
    </svg>-->

    <svg viewBox="0 0 73 73" version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" fill="#000000"><g id="SVGRepo_bgCarrier" stroke-width="0"></g><g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g><g id="SVGRepo_iconCarrier"> <title>frameworks-and-libraries/vue</title> <desc>Created with Sketch.</desc> <defs> </defs> <g id="frameworks-and-libraries/vue" stroke="none" stroke-width="1" fill="none" fill-rule="evenodd"> <g id="container" transform="translate(2.000000, 2.000000)" fill="#FFFFFF" fill-rule="nonzero" stroke="#34495E" stroke-width="2"> <rect id="mask" x="-1" y="-1" width="71" height="71" rx="14"> </rect> </g> <g id="Vue.js_Logo_2" transform="translate(11.000000, 14.000000)" fill-rule="nonzero"> <g id="Group" transform="translate(26.036066, 22.633736) scale(-1, 1) rotate(-180.000000) translate(-26.036066, -22.633736) translate(0.134827, 0.133669)"> <g transform="translate(0.015698, 0.002616)" fill="#41B883" id="Shape"> <polygon points="31.6570702 44.7385042 25.7262097 34.4659157 19.7953491 44.7385042 0.0447385042 44.7385042 25.7262097 0.256396106 51.4076808 44.7385042"> </polygon> </g> <g transform="translate(10.219216, 17.793366)" fill="#34495E" id="Shape"> <polygon points="21.4535517 26.947754 15.5226912 16.6751655 9.59183064 26.947754 0.113808475 26.947754 15.5226912 0.259012392 30.9315739 26.947754"> </polygon> </g> </g> </g> </g> </g></svg>

</template>
