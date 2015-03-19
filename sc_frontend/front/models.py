from django.db import models
from django.db.models import Q
from django.contrib.auth.models import User


class EmailAddress(models.Model):
    user = models.ForeignKey(User, null=True)
    address = models.EmailField(max_length=254)

    def __str__(self):
        return self.user.username + ': ' + self.address


class Camera(models.Model):
    user = models.ForeignKey(User)
    name = models.CharField(max_length=100)
    email_addresses = models.ManyToManyField(EmailAddress)

    def __str__(self):
        return self.user.username + ': ' + self.name;

