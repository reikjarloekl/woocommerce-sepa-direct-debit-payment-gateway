from django.contrib import admin
from django.contrib.admin import ModelAdmin
from front.models import Camera, EmailAddress

@admin.register(Camera)
class CameraAdmin(ModelAdmin):
    fields = ('user', 'name', 'email_addresses', 'smtp_password')
    readonly_fields = ['smtp_password']


admin.site.register(EmailAddress)