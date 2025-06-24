import { computed } from "vue";
import { usePage } from "@inertiajs/vue3";

export default function useAuth() {
    const page = usePage();

    const user = computed(() => page.props.auth.user);
    const role = computed(() => page.props.auth.role);
    const permissions = computed(() => page.props.auth.permissions || []);

    const hasPermission = (permissionName) => {
        return permissions.value.includes(permissionName);
    };

    const hasRole = (roleName) => {
        return role.value === roleName;
    };

    return {
        user,
        role,
        permissions,
        hasPermission,
        hasRole,
    };
}
