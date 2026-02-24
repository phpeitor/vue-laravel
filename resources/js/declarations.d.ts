// Declarar módulos CSS para que TypeScript los entienda
declare module '*.css' {
  const content: any;
  export default content;
}

declare module 'vue3-emoji-picker/css' {
  const content: any;
  export default content;
}
