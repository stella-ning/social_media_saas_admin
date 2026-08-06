<script setup>
/**
 * 超管租户选择条（纯展示 + v-model）
 * 数据加载与自动选中由 useTenantScope 负责，避免重复请求 / 重复 change
 */
defineProps({
  modelValue: { type: [Number, String, null], default: null },
  tenants: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
  label: { type: String, default: '操作租户' },
  required: { type: Boolean, default: true },
  visible: { type: Boolean, default: true }
})

defineEmits(['update:modelValue'])
</script>

<template>
  <div v-if="visible" class="tenant-scope-bar">
    <span class="label">{{ label }}</span>
    <el-select
      :model-value="modelValue"
      filterable
      clearable
      :loading="loading"
      :placeholder="required ? '请选择租户' : '全部租户'"
      style="width: 280px"
      @update:model-value="$emit('update:modelValue', $event)"
    >
      <el-option
        v-for="t in tenants"
        :key="t.id"
        :label="`${t.name}（${t.package || '-'}）`"
        :value="t.id"
      />
    </el-select>
    <span v-if="required && !modelValue" class="warn">请先选择租户后再操作</span>
  </div>
</template>

<style scoped>
.tenant-scope-bar {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 12px;
  flex-wrap: wrap;
}
.label {
  font-size: 14px;
  color: #606266;
  white-space: nowrap;
}
.warn {
  color: #e6a23c;
  font-size: 13px;
}
</style>
