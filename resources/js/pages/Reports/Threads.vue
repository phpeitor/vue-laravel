<script setup lang="ts">
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { ArrowLeft } from 'lucide-vue-next';
import { Link } from '@inertiajs/vue3';
</script>

<template>
  <div class="min-h-screen bg-background p-6">
    <Button as-child variant="ghost" class="mb-6">
      <Link href="/dashboard">
        <ArrowLeft class="w-4 h-4 mr-2" />
        Volver
      </Link>
    </Button>

    <Card>
      <CardHeader>
        <CardTitle>Reporte de Threads</CardTitle>
        <CardDescription>
          Este reporte está en desarrollo. Próximamente podrás analizar todos tus threads.
        </CardDescription>
      </CardHeader>
      <CardContent>
        <p class="text-muted-foreground">
          Aquí se mostrará un análisis detallado de los threads, incluyendo estadísticas, tiempo de resolución y más.
        </p>
      </CardContent>
    </Card>
  </div>
</template>
